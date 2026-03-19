#!/usr/bin/env node
/**
 * issue-agent/index.js
 *
 * Reads .github/issue-plan.yml and idempotently creates/updates:
 *   - Labels
 *   - Milestones
 *   - Epic issues (with auto-managed task lists)
 *   - Sub-issues (tasks)
 *   - GitHub Project v2 (adds all issues to the project)
 *
 * Issues are tracked via a hidden HTML comment in their body:
 *   <!-- plan_key: <key> -->
 *
 * The "auto-managed" section of an issue body is bounded by:
 *   <!-- BEGIN AUTO -->
 *   <!-- END AUTO -->
 * Content outside that zone is preserved on updates.
 *
 * Environment variables (set by the workflow):
 *   GITHUB_TOKEN  – token with issues/milestones write access
 *   GITHUB_OWNER  – repository owner (e.g. "WebJax")
 *   GITHUB_REPO   – repository name  (e.g. "jxw-mall")
 *   PROJECT_TOKEN – (optional) PAT with `project` scope for Projects v2
 *                   Falls back to GITHUB_TOKEN if not set.
 *   PLAN_FILE     – path to plan YAML, default ".github/issue-plan.yml"
 */

"use strict";

const fs = require("fs");
const path = require("path");
const yaml = require("js-yaml");
const { Octokit } = require("@octokit/rest");
const { graphql } = require("@octokit/graphql");

// ─── Configuration ────────────────────────────────────────────────────────────

const OWNER = process.env.GITHUB_OWNER;
const REPO = process.env.GITHUB_REPO;
const TOKEN = process.env.GITHUB_TOKEN;
const PROJECT_TOKEN = process.env.PROJECT_TOKEN || TOKEN;

if (!OWNER || !REPO || !TOKEN) {
  console.error(
    "ERROR: GITHUB_OWNER, GITHUB_REPO, and GITHUB_TOKEN must be set."
  );
  process.exit(1);
}

const PLAN_FILE =
  process.env.PLAN_FILE ||
  path.join(process.cwd(), ".github", "issue-plan.yml");

const AUTO_BEGIN = "<!-- BEGIN AUTO -->";
const AUTO_END = "<!-- END AUTO -->";
const PLAN_KEY_RE = /<!--\s*plan_key:\s*([^\s>]+)\s*-->/;

// ─── API clients ──────────────────────────────────────────────────────────────

const octokit = new Octokit({ auth: TOKEN });

const gql = graphql.defaults({
  headers: { authorization: `token ${PROJECT_TOKEN}` },
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function log(action, subject) {
  const icon = action === "create" ? "✅ CREATE" : "🔄 UPDATE";
  console.log(`${icon}  ${subject}`);
}

function planKeyComment(key) {
  return `<!-- plan_key: ${key} -->`;
}

/** Wrap content in the AUTO zone markers */
function autoZone(content) {
  return `${AUTO_BEGIN}\n${content.trim()}\n${AUTO_END}`;
}

/**
 * Replace (or insert) the AUTO zone in an existing body.
 * Content outside the zone is preserved.
 */
function mergeAutoZone(existingBody, newAutoContent) {
  const begin = existingBody.indexOf(AUTO_BEGIN);
  const end = existingBody.indexOf(AUTO_END);

  if (begin !== -1 && end !== -1) {
    const before = existingBody.slice(0, begin);
    const after = existingBody.slice(end + AUTO_END.length);
    return before + autoZone(newAutoContent) + after;
  }
  // No zone yet – append it
  return `${existingBody.trim()}\n\n${autoZone(newAutoContent)}`;
}

/**
 * Fetch all pages of a paginated REST endpoint.
 */
async function paginate(method, params) {
  return octokit.paginate(method, params);
}

// ─── Labels ───────────────────────────────────────────────────────────────────

async function ensureLabels(labelsSpec) {
  console.log("\n── Labels ───────────────────────────────────────────────────");

  const existing = await paginate(octokit.issues.listLabelsForRepo, {
    owner: OWNER,
    repo: REPO,
    per_page: 100,
  });
  const existingMap = new Map(existing.map((l) => [l.name, l]));

  for (const spec of labelsSpec) {
    const color = spec.color.replace(/^#/, "");
    if (existingMap.has(spec.name)) {
      const cur = existingMap.get(spec.name);
      if (cur.color !== color || cur.description !== (spec.description || "")) {
        await octokit.issues.updateLabel({
          owner: OWNER,
          repo: REPO,
          name: spec.name,
          color,
          description: spec.description || "",
        });
        log("update", `label "${spec.name}"`);
      } else {
        console.log(`  ⬜ SKIP   label "${spec.name}" (no changes)`);
      }
    } else {
      await octokit.issues.createLabel({
        owner: OWNER,
        repo: REPO,
        name: spec.name,
        color,
        description: spec.description || "",
      });
      log("create", `label "${spec.name}"`);
    }
  }
}

// ─── Milestones ───────────────────────────────────────────────────────────────

async function ensureMilestones(milestonesSpec) {
  console.log(
    "\n── Milestones ───────────────────────────────────────────────"
  );

  const existing = await paginate(octokit.issues.listMilestones, {
    owner: OWNER,
    repo: REPO,
    state: "open",
    per_page: 100,
  });
  // Also fetch closed milestones so we don't duplicate them
  const existingClosed = await paginate(octokit.issues.listMilestones, {
    owner: OWNER,
    repo: REPO,
    state: "closed",
    per_page: 100,
  });
  const allMilestones = [...existing, ...existingClosed];
  const byTitle = new Map(allMilestones.map((m) => [m.title, m]));

  const milestoneNumbers = {};

  for (const spec of milestonesSpec) {
    if (byTitle.has(spec.title)) {
      const cur = byTitle.get(spec.title);
      milestoneNumbers[spec.key] = cur.number;
      const desc = (spec.description || "").trim();
      if (cur.description !== desc) {
        await octokit.issues.updateMilestone({
          owner: OWNER,
          repo: REPO,
          milestone_number: cur.number,
          description: desc,
        });
        log("update", `milestone "${spec.title}"`);
      } else {
        console.log(`  ⬜ SKIP   milestone "${spec.title}" (no changes)`);
      }
    } else {
      const created = await octokit.issues.createMilestone({
        owner: OWNER,
        repo: REPO,
        title: spec.title,
        description: (spec.description || "").trim(),
        ...(spec.due_on ? { due_on: spec.due_on } : {}),
      });
      milestoneNumbers[spec.key] = created.data.number;
      log("create", `milestone "${spec.title}"`);
    }
  }

  return milestoneNumbers;
}

// ─── Issues (tasks + epics) ───────────────────────────────────────────────────

/**
 * Build a map of plan_key → issue number by searching all repo issues.
 * Uses the hidden HTML comment in the issue body for identification.
 */
async function buildPlanKeyMap() {
  const allIssues = await paginate(octokit.issues.listForRepo, {
    owner: OWNER,
    repo: REPO,
    state: "all",
    per_page: 100,
  });
  const map = new Map();
  for (const issue of allIssues) {
    if (!issue.body) continue;
    const match = PLAN_KEY_RE.exec(issue.body);
    if (match) map.set(match[1], issue);
  }
  return map;
}

/**
 * Ensure a single issue exists and is up-to-date.
 * Returns the issue number.
 */
async function ensureIssue(spec, milestoneNumber, planKeyMap) {
  const keyComment = planKeyComment(spec.key);
  const labels = spec.labels || [];

  if (planKeyMap.has(spec.key)) {
    // UPDATE path
    const existing = planKeyMap.get(spec.key);
    const issueNumber = existing.number;

    const updates = {};
    if (existing.title !== spec.title) updates.title = spec.title;

    const existingMilestone = existing.milestone
      ? existing.milestone.number
      : null;
    if (existingMilestone !== milestoneNumber)
      updates.milestone = milestoneNumber;

    const existingLabels = (existing.labels || []).map((l) => l.name).sort();
    if (JSON.stringify(existingLabels) !== JSON.stringify([...labels].sort()))
      updates.labels = labels;

    if (Object.keys(updates).length > 0) {
      await octokit.issues.update({
        owner: OWNER,
        repo: REPO,
        issue_number: issueNumber,
        ...updates,
      });
      log("update", `issue #${issueNumber} "${spec.title}"`);
    } else {
      console.log(
        `  ⬜ SKIP   issue #${issueNumber} "${spec.title}" (no changes)`
      );
    }
    return issueNumber;
  }

  // CREATE path
  const body = buildIssueBody(spec, keyComment);
  const created = await octokit.issues.create({
    owner: OWNER,
    repo: REPO,
    title: spec.title,
    body,
    labels,
    milestone: milestoneNumber,
  });
  log("create", `issue #${created.data.number} "${spec.title}"`);
  planKeyMap.set(spec.key, created.data);
  return created.data.number;
}

/** Build the full body for a new issue */
function buildIssueBody(spec, keyComment) {
  const baseBody = (spec.body || "").trim();
  const autoContent = ""; // empty for task issues on creation
  return `${keyComment}\n\n${baseBody}\n\n${autoZone(autoContent)}`;
}

/**
 * Update an epic issue's AUTO zone with the current task list.
 * Preserves all content outside the AUTO zone.
 */
async function updateEpicTaskList(epicNumber, taskNumbers, planKeyMap) {
  const issue = await octokit.issues.get({
    owner: OWNER,
    repo: REPO,
    issue_number: epicNumber,
  });
  const existingBody = issue.data.body || "";

  const taskListLines = taskNumbers
    .map(({ title, number }) => `- [ ] #${number} ${title}`)
    .join("\n");
  const newAutoContent = `## Tasks\n\n${taskListLines}`;

  const newBody = mergeAutoZone(existingBody, newAutoContent);
  if (newBody !== existingBody) {
    await octokit.issues.update({
      owner: OWNER,
      repo: REPO,
      issue_number: epicNumber,
      body: newBody,
    });
    log("update", `epic #${epicNumber} task list`);
  } else {
    console.log(
      `  ⬜ SKIP   epic #${epicNumber} task list (no changes)`
    );
  }
}

// ─── GitHub Projects v2 ───────────────────────────────────────────────────────

async function getOwnerNodeId() {
  const resp = await gql(
    `query($login: String!) {
      repositoryOwner(login: $login) { id }
    }`,
    { login: OWNER }
  );
  return resp.repositoryOwner.id;
}

async function getRepoNodeId() {
  const resp = await gql(
    `query($owner: String!, $name: String!) {
      repository(owner: $owner, name: $name) { id }
    }`,
    { owner: OWNER, name: REPO }
  );
  return resp.repository.id;
}

async function findOrCreateProject(projectName, ownerNodeId) {
  // Search existing projects for the owner
  const resp = await gql(
    `query($login: String!) {
      repositoryOwner(login: $login) {
        ... on User {
          projectsV2(first: 50) {
            nodes { id title }
          }
        }
        ... on Organization {
          projectsV2(first: 50) {
            nodes { id title }
          }
        }
      }
    }`,
    { login: OWNER }
  );

  const nodes =
    resp.repositoryOwner.projectsV2
      ? resp.repositoryOwner.projectsV2.nodes
      : [];
  const existing = nodes.find((p) => p.title === projectName);

  if (existing) {
    console.log(`  ⬜ SKIP   project "${projectName}" (already exists)`);
    return existing.id;
  }

  const created = await gql(
    `mutation($ownerId: ID!, $title: String!) {
      createProjectV2(input: { ownerId: $ownerId, title: $title }) {
        projectV2 { id title }
      }
    }`,
    { ownerId: ownerNodeId, title: projectName }
  );
  log("create", `project "${projectName}"`);
  return created.createProjectV2.projectV2.id;
}

async function getIssueNodeId(issueNumber) {
  const resp = await gql(
    `query($owner: String!, $name: String!, $number: Int!) {
      repository(owner: $owner, name: $name) {
        issue(number: $number) { id }
      }
    }`,
    { owner: OWNER, name: REPO, number: issueNumber }
  );
  return resp.repository.issue.id;
}

async function addIssueToProject(projectId, issueNodeId, issueNumber) {
  try {
    await gql(
      `mutation($projectId: ID!, $contentId: ID!) {
        addProjectV2ItemById(input: { projectId: $projectId, contentId: $contentId }) {
          item { id }
        }
      }`,
      { projectId, contentId: issueNodeId }
    );
    console.log(`  ✅ CREATE  added issue #${issueNumber} to project`);
  } catch (err) {
    // "already in project" is not an error for us
    if (err.message && err.message.includes("already")) {
      console.log(
        `  ⬜ SKIP   issue #${issueNumber} already in project`
      );
    } else {
      console.warn(
        `  ⚠️  WARN   failed to add issue #${issueNumber} to project: ${err.message}`
      );
    }
  }
}

// ─── Main ─────────────────────────────────────────────────────────────────────

async function main() {
  console.log(`\n🚀 Issue Agent – ${OWNER}/${REPO}`);
  console.log(`   Plan file: ${PLAN_FILE}\n`);

  if (!fs.existsSync(PLAN_FILE)) {
    console.error(`ERROR: Plan file not found: ${PLAN_FILE}`);
    process.exit(1);
  }

  const plan = yaml.load(fs.readFileSync(PLAN_FILE, "utf8"));

  // 1. Labels
  if (plan.labels && plan.labels.length) {
    await ensureLabels(plan.labels);
  }

  // 2. Milestones
  let milestoneNumbers = {};
  if (plan.milestones && plan.milestones.length) {
    milestoneNumbers = await ensureMilestones(plan.milestones);
  }

  // 3. Build existing plan_key → issue map (single pass, reused throughout)
  console.log("\n── Indexing existing issues ──────────────────────────────────");
  const planKeyMap = await buildPlanKeyMap();
  console.log(`   Found ${planKeyMap.size} issues with plan_key comments.`);

  // 4. Epics + tasks
  console.log("\n── Epics & Tasks ────────────────────────────────────────────");
  const allIssueNumbers = []; // collect all issue numbers for project linking

  for (const epic of plan.epics || []) {
    const milestoneNumber = milestoneNumbers[epic.milestone] || null;

    // Ensure epic issue
    const epicNumber = await ensureIssue(epic, milestoneNumber, planKeyMap);
    allIssueNumbers.push(epicNumber);

    // Ensure each task sub-issue
    const taskEntries = [];
    for (const task of epic.tasks || []) {
      const taskNumber = await ensureIssue(task, milestoneNumber, planKeyMap);
      taskEntries.push({ title: task.title, number: taskNumber });
      allIssueNumbers.push(taskNumber);
    }

    // Update epic body with task list in AUTO zone
    if (taskEntries.length > 0) {
      await updateEpicTaskList(epicNumber, taskEntries, planKeyMap);
    }
  }

  // 5. GitHub Project v2
  console.log(
    "\n── GitHub Project v2 ────────────────────────────────────────"
  );
  const projectName = plan.project ? plan.project.name : "Issue Plan";

  let projectOk = true;
  let projectId = null;

  try {
    const ownerNodeId = await getOwnerNodeId();
    projectId = await findOrCreateProject(projectName, ownerNodeId);

    // Deduplicate issue numbers
    const uniqueNumbers = [...new Set(allIssueNumbers)];
    for (const num of uniqueNumbers) {
      const nodeId = await getIssueNodeId(num);
      await addIssueToProject(projectId, nodeId, num);
    }
  } catch (err) {
    projectOk = false;
    console.warn(
      `\n  ⚠️  WARN   Projects v2 operations failed: ${err.message}`
    );
    console.warn(
      "  If you see a 403 or scope error, set the PROJECT_TOKEN secret"
    );
    console.warn("  to a PAT with the 'project' scope. See README.md.\n"
    );
  }

  console.log("\n────────────────────────────────────────────────────────────");
  console.log("✅ Issue Agent finished.");
  if (!projectOk) {
    console.log(
      "⚠️  Projects v2 step had warnings (see above). All other steps succeeded."
    );
  }
}

main().catch((err) => {
  console.error("FATAL:", err);
  process.exit(1);
});
