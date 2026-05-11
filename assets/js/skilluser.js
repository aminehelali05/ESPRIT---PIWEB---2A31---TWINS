(() => {
  const DEFAULT_SKILLS = [
    'JavaScript', 'TypeScript', 'PHP', 'Python', 'React', 'Node.js', 'SQL', 'UI/UX',
    'Figma', 'Project Management', 'AI Prompting', 'DevOps', 'Testing', 'Communication'
  ];

  const ensureStyles = () => {
    if (document.getElementById('skilluser-styles')) return;

    const style = document.createElement('style');
    style.id = 'skilluser-styles';
    style.textContent = `
      .skilluser-wrap { display:grid; gap:10px; }
      .skilluser-chip-list { display:flex; flex-wrap:wrap; gap:8px; min-height:38px; }
      .skilluser-chip {
        display:inline-flex; align-items:center; gap:6px;
        padding:6px 10px; border-radius:999px;
        border:1px solid rgba(79,82,217,0.26);
        background:linear-gradient(135deg, rgba(79,82,217,0.12), rgba(14,165,233,0.1));
        color:#243b7a; font-weight:600; font-size:12px;
        transform:translateY(6px) scale(0.94); opacity:0;
        animation:skillChipIn .26s ease forwards;
      }
      .skilluser-chip button {
        width:18px; height:18px; border-radius:50%; border:none;
        display:inline-flex; align-items:center; justify-content:center;
        background:rgba(15,23,42,0.12); color:#0f172a; cursor:pointer;
      }
      .skilluser-input-row { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
      .skilluser-input {
        min-width:180px; flex:1; border:1px solid rgba(30,50,120,0.14);
        border-radius:10px; padding:8px 10px; background:#fff;
      }
      .skilluser-suggest { display:flex; flex-wrap:wrap; gap:7px; }
      .skilluser-pill {
        border:1px solid rgba(30,50,120,0.14); background:#fff; color:#334155;
        border-radius:999px; padding:6px 10px; font-size:12px; font-weight:600;
        cursor:pointer; transition:all .18s ease;
      }
      .skilluser-pill:hover { border-color:rgba(79,82,217,0.38); transform:translateY(-1px); }
      @keyframes skillChipIn { to { transform:translateY(0) scale(1); opacity:1; } }
    `;

    document.head.appendChild(style);
  };

  const parseSkills = (value) => String(value || '')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);

  const initField = (field) => {
    if (!field || field.dataset.skilluserReady === '1') return;
    field.dataset.skilluserReady = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'skilluser-wrap';

    const chips = document.createElement('div');
    chips.className = 'skilluser-chip-list';

    const inputRow = document.createElement('div');
    inputRow.className = 'skilluser-input-row';

    const textInput = document.createElement('input');
    textInput.type = 'text';
    textInput.className = 'skilluser-input';
    textInput.placeholder = 'Type a skill and press Enter...';

    const suggest = document.createElement('div');
    suggest.className = 'skilluser-suggest';

    const selected = new Set(parseSkills(field.value));

    const syncField = () => {
      field.value = Array.from(selected).join(', ');
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = () => {
      chips.innerHTML = '';
      Array.from(selected).forEach((skill) => {
        const chip = document.createElement('span');
        chip.className = 'skilluser-chip';
        chip.innerHTML = `<span>${skill}</span>`;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', () => {
          selected.delete(skill);
          render();
          syncField();
        });

        chip.appendChild(removeBtn);
        chips.appendChild(chip);
      });

      suggest.innerHTML = '';
      DEFAULT_SKILLS.filter((s) => !selected.has(s)).slice(0, 8).forEach((skill) => {
        const pill = document.createElement('button');
        pill.type = 'button';
        pill.className = 'skilluser-pill';
        pill.textContent = `+ ${skill}`;
        pill.addEventListener('click', () => {
          selected.add(skill);
          render();
          syncField();
        });
        suggest.appendChild(pill);
      });
    };

    const addSkill = (raw) => {
      const value = String(raw || '').trim();
      if (!value) return;
      if (value.length > 36) return;
      selected.add(value);
      textInput.value = '';
      render();
      syncField();
    };

    textInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addSkill(textInput.value);
      }
    });

    textInput.addEventListener('blur', () => addSkill(textInput.value));

    inputRow.appendChild(textInput);
    wrapper.appendChild(chips);
    wrapper.appendChild(inputRow);
    wrapper.appendChild(suggest);

    field.style.display = 'none';
    field.parentElement.appendChild(wrapper);

    render();
    syncField();
  };

  document.addEventListener('DOMContentLoaded', () => {
    ensureStyles();
    const fields = Array.from(document.querySelectorAll('input[data-interactive-skills="1"], #formSkills'));
    fields.forEach(initField);
  });
})();
