/* global ajaxurl */
(function () {
  'use strict';

  // -------------------------------------------------------------------------
  // Day rows – inherit toggle + status select
  // -------------------------------------------------------------------------

  function updateDayRow(row) {
    const inherit = row.querySelector('.jxw-oh-inherit-toggle').checked;
    const statusSelect = row.querySelector('.jxw-oh-status-select');
    const timeInputs = row.querySelectorAll('.jxw-oh-time-input');
    const isClosed = statusSelect && statusSelect.value === 'closed';

    row.classList.toggle('jxw-oh-inherited', inherit);
    statusSelect.disabled = inherit;
    timeInputs.forEach(function (inp) {
      inp.disabled = inherit || isClosed;
    });
  }

  document.querySelectorAll('.jxw-oh-day-row').forEach(function (row) {
    // Initial state
    updateDayRow(row);

    row.querySelector('.jxw-oh-inherit-toggle').addEventListener('change', function () {
      updateDayRow(row);
    });

    const statusSelect = row.querySelector('.jxw-oh-status-select');
    if (statusSelect) {
      statusSelect.addEventListener('change', function () {
        updateDayRow(row);
      });
    }
  });

  // -------------------------------------------------------------------------
  // Exceptions – add / remove rows
  // -------------------------------------------------------------------------

  let excIndex = document.querySelectorAll('.jxw-oh-exc-row').length;

  function buildExcRow(idx) {
    const tr = document.createElement('tr');
    tr.className = 'jxw-oh-exc-row';
    tr.innerHTML =
      '<td><input type="date" name="butik_oh_exceptions[' + idx + '][from_date]" class="jxw-oh-exc-input" /></td>' +
      '<td><input type="date" name="butik_oh_exceptions[' + idx + '][to_date]" class="jxw-oh-exc-input" /></td>' +
      '<td>' +
        '<select name="butik_oh_exceptions[' + idx + '][status]" class="jxw-oh-exc-status">' +
          '<option value="closed" selected>Lukket</option>' +
          '<option value="open">Åben</option>' +
        '</select>' +
      '</td>' +
      '<td><input type="time" name="butik_oh_exceptions[' + idx + '][from]" class="jxw-oh-exc-time" disabled /></td>' +
      '<td><input type="time" name="butik_oh_exceptions[' + idx + '][to]" class="jxw-oh-exc-time" disabled /></td>' +
      '<td><input type="text" name="butik_oh_exceptions[' + idx + '][label]" placeholder="f.eks. Sommerlukket" class="jxw-oh-exc-input" /></td>' +
      '<td><button type="button" class="button button-small jxw-oh-remove-exc">Fjern</button></td>';
    return tr;
  }

  function updateExcRowTimes(row) {
    const status = row.querySelector('.jxw-oh-exc-status').value;
    row.querySelectorAll('.jxw-oh-exc-time').forEach(function (inp) {
      inp.disabled = status === 'closed';
    });
  }

  function bindExcRow(row) {
    const statusSelect = row.querySelector('.jxw-oh-exc-status');
    if (statusSelect) {
      statusSelect.addEventListener('change', function () {
        updateExcRowTimes(row);
      });
      updateExcRowTimes(row);
    }

    row.querySelector('.jxw-oh-remove-exc').addEventListener('click', function () {
      row.remove();
    });
  }

  // Bind existing rows (rendered server-side)
  document.querySelectorAll('.jxw-oh-exc-row').forEach(bindExcRow);

  const addBtn = document.getElementById('jxw-oh-add-exc');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      const tbody = document.getElementById('jxw-oh-exceptions-body');
      const row = buildExcRow(excIndex++);
      tbody.appendChild(row);
      bindExcRow(row);
    });
  }

  // -------------------------------------------------------------------------
  // Migration button
  // -------------------------------------------------------------------------

  const migrateBtn = document.getElementById('jxw-migrate-btn');
  if (migrateBtn) {
    migrateBtn.addEventListener('click', function () {
      const btn = this;
      const resultEl = document.querySelector('.jxw-oh-migrate-result');
      btn.disabled = true;
      btn.textContent = 'Konverterer…';

      const data = new URLSearchParams({
        action: 'jxw_migrate_opening_hours',
        nonce: btn.dataset.nonce,
        post_id: btn.dataset.postId,
      });

      fetch(ajaxurl, { method: 'POST', body: data })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json.success) {
            resultEl.textContent = json.data.message;
            resultEl.style.color = 'green';
            setTimeout(function () { location.reload(); }, 1200);
          } else {
            resultEl.textContent = json.data.message || 'Fejl.';
            resultEl.style.color = 'red';
            btn.disabled = false;
            btn.textContent = 'Konvertér nu';
          }
        })
        .catch(function () {
          resultEl.textContent = 'Netværksfejl.';
          resultEl.style.color = 'red';
          btn.disabled = false;
          btn.textContent = 'Konvertér nu';
        });
    });
  }
})();
