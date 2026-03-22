# Issue Agent

An idempotent GitHub automation that reads a YAML plan file and creates/updates
Labels, Milestones, Epic issues, Sub-issues, and a GitHub Project board (v2)
for the `WebJax/jxw-mall` internal WordPress plugin project.

---

## Quick start

1. **Run the workflow manually**
   - Go to your repository on GitHub.
   - Click **Actions → Issue Agent → Run workflow**.
   - Leave the `plan_file` input as the default (`.github/issue-plan.yml`) and click **Run workflow**.

2. **Automatic sync on plan file changes**
   - Any push to `main`/`master` that modifies `.github/issue-plan.yml` will trigger the workflow automatically.

---

## How it works

```
.github/issue-plan.yml        ← the single source of truth
        │
        ▼
scripts/issue-agent/index.js  ← Node.js agent script
        │
        ├── Creates / updates Labels
        ├── Creates / updates Milestones
        ├── Creates / updates Epic issues   (one per milestone)
        ├── Creates / updates Task issues   (sub-issues)
        ├── Updates Epic body with task list
        └── Creates / updates GitHub Project v2 and adds all issues
```

### Idempotency

Every issue contains a hidden HTML comment that acts as a stable identifier:

```html
<!-- plan_key: M1-db-schema -->
```

When the agent runs, it first fetches **all** existing issues and builds a
`plan_key → issue` map. For each item in the plan:

- If an issue with that `plan_key` exists → **update** title / labels / milestone if needed.
- If no matching issue exists → **create** a new one.

Running the workflow multiple times never creates duplicates.

### Auto-managed zone

The agent owns a delimited section of each issue body:

```markdown
<!-- BEGIN AUTO -->
## Tasks

- [ ] #12 Bootstrap plugin structure + autoload
- [ ] #13 Define constants + environment detection
<!-- END AUTO -->
```

**Everything outside this zone is preserved** on updates, so you can freely
add your own notes, comments, or acceptance criteria below (or above) it
without the agent overwriting them.

---

## Plan file structure (`.github/issue-plan.yml`)

```yaml
project:
  name: "My Project Name"
  description: "..."

labels:
  - name: "type:feature"
    color: "0075ca"           # hex without '#'
    description: "New feature"

milestones:
  - key: M1                   # internal key used to link issues → milestone
    title: "M1 - Foundation (v0.1)"
    description: "Short description"
    due_on: "2025-06-01"      # optional, ISO 8601

epics:
  - key: M1-epic              # plan_key for this epic issue
    milestone: M1             # references milestones[].key
    title: "[Epic] M1 - Foundation (v0.1)"
    labels:
      - "type:epic"
    body: |
      ## Formål
      ...

    tasks:
      - key: M1-db-schema     # plan_key for this task issue
        title: "DB schema proposal"
        labels:
          - "type:chore"
          - "area:db"
        body: |
          ## Opgave
          ...
```

### Field reference

| Field | Required | Description |
|---|---|---|
| `project.name` | yes | Name of the GitHub Project v2 to create/use |
| `labels[].name` | yes | Label name (created if missing) |
| `labels[].color` | yes | Hex color without `#` |
| `labels[].description` | no | Label description |
| `milestones[].key` | yes | Internal key for cross-referencing |
| `milestones[].title` | yes | Milestone title |
| `milestones[].due_on` | no | Due date (ISO 8601) |
| `epics[].key` | yes | Unique plan key (used as idempotency key) |
| `epics[].milestone` | yes | References `milestones[].key` |
| `epics[].title` | yes | Issue title |
| `epics[].labels` | no | List of label names |
| `epics[].body` | no | Issue body (markdown) |
| `epics[].tasks[]` | no | Sub-issues for this epic |
| `tasks[].key` | yes | Unique plan key |
| `tasks[].title` | yes | Issue title |
| `tasks[].labels` | no | List of label names |
| `tasks[].body` | no | Issue body (markdown) |

---

## Adding new milestones or tasks

1. Edit `.github/issue-plan.yml`.
2. Add a new entry under `milestones:` and/or `epics[].tasks:`.
3. Commit and push to `main` – the workflow triggers automatically.
   Or run it manually via **Actions → Issue Agent → Run workflow**.

The agent will create only the new items and leave existing ones untouched
(unless their title/labels/milestone have changed).

---

## GitHub Projects v2 setup

The agent uses the **GraphQL API** to create/find the project and add issues.

### Permissions

| Operation | Token needed |
|---|---|
| Labels, Milestones, Issues | `GITHUB_TOKEN` (automatic, no setup required) |
| GitHub Projects v2 | PAT with `project` scope **or** `GITHUB_TOKEN` if org allows it |

### Using a PAT for Projects v2

If the automatic `GITHUB_TOKEN` lacks the `project` scope (common in
organisation repositories):

1. Create a **Fine-grained Personal Access Token** (recommended):
   - Repository permissions: **Contents: Read**, **Issues: Read and Write**
   - Organization/owner permissions: **Projects: Read and Write**

   Alternatively, create a **Classic Personal Access Token** with scopes:
   `repo`, `project`.

2. Add it as a repository secret named **`PROJECT_TOKEN`**
   (Settings → Secrets and variables → Actions → New repository secret).
3. The workflow will automatically use it for the Projects v2 GraphQL calls.

If `PROJECT_TOKEN` is not set, the agent falls back to `GITHUB_TOKEN` and
logs a warning if the project step fails. All other steps (labels, milestones,
issues) still succeed.

---

## Running locally

```bash
# Prerequisites: Node.js ≥ 18, a GitHub token
export GITHUB_TOKEN=ghp_...
export GITHUB_OWNER=WebJax
export GITHUB_REPO=jxw-mall
export PLAN_FILE=$(pwd)/.github/issue-plan.yml

cd scripts/issue-agent
npm ci
node index.js
```

---

## Script overview (`scripts/issue-agent/index.js`)

| Function | Description |
|---|---|
| `ensureLabels()` | Creates/updates labels from the plan |
| `ensureMilestones()` | Creates/updates milestones, returns `key → number` map |
| `buildPlanKeyMap()` | Scans all issues for `<!-- plan_key: ... -->` comments |
| `ensureIssue()` | Creates or updates a single issue |
| `updateEpicTaskList()` | Rewrites the AUTO zone in an epic issue with the current task list |
| `findOrCreateProject()` | GraphQL: finds or creates a GitHub Project v2 |
| `addIssueToProject()` | GraphQL: adds an issue to the project (skips if already added) |
| `main()` | Orchestrates all of the above |
