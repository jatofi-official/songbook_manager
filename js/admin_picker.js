// Interactive "search users by name, add/remove as chips" picker. Shared
// between create_songbook.php and songbook.php's admin-editing UI so admins
// are always picked the same way instead of pasting comma-separated ids.
function createAdminPicker(root, options) {
    options = options || {};
    const initialAdmins = options.initialAdmins || [];
    const onChange = options.onChange || function () {};
    const protectedIds = (options.protectedIds || []).map(id => parseInt(id, 10));

    const chipsEl = root.querySelector('.admin-picker-chips');
    const input = root.querySelector('.admin-picker-input');
    const resultsEl = root.querySelector('.admin-picker-results');

    let admins = initialAdmins.map(a => ({
        id: parseInt(a.id, 10),
        name: a.name,
        username: a.username
    }));
    let debounceTimer = null;

    function emitChange() {
        onChange(admins.map(a => a.id));
    }

    function renderChips() {
        chipsEl.innerHTML = '';
        admins.forEach(admin => {
            const chip = document.createElement('span');
            chip.className = 'badge bg-primary d-flex align-items-center gap-1 py-2 px-2';
            chip.textContent = admin.name || admin.username;

            if (!protectedIds.includes(admin.id)) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-close btn-close-white';
                removeBtn.style.fontSize = '0.55rem';
                removeBtn.setAttribute('aria-label', 'Remove admin');
                removeBtn.addEventListener('click', () => {
                    admins = admins.filter(a => a.id !== admin.id);
                    renderChips();
                    emitChange();
                });
                chip.appendChild(removeBtn);
            }
            chipsEl.appendChild(chip);
        });
    }

    function hideResults() {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
    }

    function addAdmin(user) {
        if (admins.some(a => a.id === user.id)) return;
        admins.push(user);
        renderChips();
        emitChange();
        input.value = '';
        hideResults();
        input.focus();
    }

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (!q) {
            hideResults();
            return;
        }
        debounceTimer = setTimeout(async () => {
            let users;
            try {
                const res = await fetch('php/subpage/search_users.php?q=' + encodeURIComponent(q));
                users = await res.json();
            } catch (err) {
                console.error('Admin search failed:', err);
                return;
            }
            const available = users.filter(u => !admins.some(a => a.id === u.id));
            resultsEl.innerHTML = '';
            if (!available.length) {
                hideResults();
                return;
            }
            available.forEach(user => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = user.username && user.username !== user.name
                    ? `${user.name} (@${user.username})`
                    : user.name;
                item.addEventListener('click', () => addAdmin(user));
                resultsEl.appendChild(item);
            });
            resultsEl.style.display = 'block';
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) hideResults();
    });

    renderChips();

    return {
        getIds: () => admins.map(a => a.id),
        getAdmins: () => admins.map(a => ({ ...a })),
        // Replaces the current admin list wholesale (e.g. to discard unsaved
        // edits when a modal hosting this picker is reopened).
        setAdmins: (list) => {
            admins = list.map(a => ({
                id: parseInt(a.id, 10),
                name: a.name,
                username: a.username
            }));
            hideResults();
            renderChips();
        }
    };
}
