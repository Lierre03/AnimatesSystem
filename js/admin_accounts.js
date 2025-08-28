
(() => {
  const listEl = document.getElementById('list');
  const filterRole = document.getElementById('filterRole');
  const btnRefresh = document.getElementById('btnRefresh');
  const btnNew = document.getElementById('btnNew');
  const dlg = document.getElementById('dlg');
  const frm = document.getElementById('frm');
  const roleSel = document.getElementById('role');
  const staffRoleSel = null; // removed
  const btnCancel = document.getElementById('btnCancel');
  const totalUsersEl = document.getElementById('totalUsers');
  const activeUsersEl = document.getElementById('activeUsers');

  const token = localStorage.getItem('auth_token');
  if (!token) { window.location.href = 'admin_staff_auth.html'; return; }

  function authFetch(body) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
    
    return fetch('http://localhost/animates/api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify(body),
      signal: controller.signal
    })
    .then(r => {
      clearTimeout(timeoutId);
      return r.json().then(j => ({ ok: r.ok, data: j }));
    })
    .then(({ ok, data }) => {
      if (!ok || !data.success) throw new Error(data.error || 'Request failed');
      return data;
    })
    .catch(error => {
      clearTimeout(timeoutId);
      if (error.name === 'AbortError') {
        throw new Error('Request timed out. Please try again.');
      }
      throw error;
    });
  }

  function updateStats(users) {
    if (!Array.isArray(users)) {
      totalUsersEl.textContent = '-';
      activeUsersEl.textContent = '-';
      return;
    }
    
    const total = users.length;
    const active = users.filter(u => u.is_active).length;
    
    totalUsersEl.textContent = total;
    activeUsersEl.textContent = active;
  }

  function getRoleBadge(role, staffRole, isActive) {
    const baseClasses = 'inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium';
    
    if (!isActive) {
      return `<span class="${baseClasses} bg-red-100 text-red-700 border border-red-200">Inactive</span>`;
    }
    
    let badgeClasses = '';
    let icon = '';
    
    switch (role) {
      case 'admin':
        badgeClasses = 'bg-purple-100 text-purple-700 border border-purple-200';
        icon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>';
        break;
      case 'cashier':
        badgeClasses = 'bg-blue-100 text-blue-700 border border-blue-200';
        icon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>';
        break;
      case 'customer':
        badgeClasses = 'bg-green-100 text-green-700 border border-green-200';
        icon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
        break;
      default:
        badgeClasses = 'bg-gray-100 text-gray-700 border border-gray-200';
        icon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>';
        break;
    }
    
    let badge = `<span class="${baseClasses} ${badgeClasses}">${icon} ${role}`;
    if (staffRole) {
      badge += ` · ${staffRole}`;
    }
    badge += '</span>';
    
    return badge;
  }

  function render(users) {
    listEl.innerHTML = '';
    
    // Validate users array
    if (!Array.isArray(users)) {
      listEl.innerHTML = `
        <div class="text-center py-8 text-red-500">
          <svg class="w-12 h-12 mx-auto mb-3 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
          </svg>
          <p class="text-lg font-medium">Error loading users</p>
          <p class="text-sm">Invalid data received from server</p>
        </div>
      `;
      return;
    }
    
    if (users.length === 0) {
      listEl.innerHTML = `
        <div class="text-center py-8 text-gray-500">
          <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
          <p class="text-lg font-medium">No users found</p>
          <p class="text-sm">Try adjusting your filters or create a new user</p>
        </div>
      `;
      return;
    }
    
    users.forEach(u => {
      // Prioritize full_name for display, fallback to username if full_name is empty
      const displayName = (typeof u.full_name === 'string' && u.full_name.trim())
        ? u.full_name.trim()
        : ((typeof u.username === 'string' && u.username.trim()) ? u.username.trim() : 'User');
      
      // Get initials from full name (first letter of each word)
      const nameParts = displayName.split(' ').filter(part => part.trim());
      const initials = nameParts.length > 1 
        ? (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase()
        : displayName.charAt(0).toUpperCase();
      
      const card = document.createElement('div');
      card.className = 'bg-white/80 backdrop-blur-sm border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all duration-200';
      if (u.id) { card.setAttribute('data-user-id', String(u.id)); }
      
      card.innerHTML = `
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 bg-gradient-to-br from-gold-100 to-gold-200 rounded-full flex items-center justify-center">
                <span class="text-gold-700 font-semibold text-sm">${initials}</span>
              </div>
              <div>
                <div class="font-semibold text-gray-900">${displayName}</div>
                <div class="text-sm text-gray-600">${u.email}</div>
                ${u.username && u.username !== u.email ? `<div class="text-xs text-gray-500">Username: ${u.username}</div>` : ''}
              </div>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-3">
              <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                ${u.role.charAt(0).toUpperCase() + u.role.slice(1)}
              </span>
              <span class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                ${u.created_at ? new Date(u.created_at).toLocaleDateString() : 'N/A'}
              </span>
            </div>
            <div class="flex items-center gap-2">
              ${getRoleBadge(u.role, null, u.is_active)}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button data-act="role" data-id="${u.id}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-200 font-medium">
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
              </svg>
              Role
            </button>
            <button data-act="toggle" data-id="${u.id}" data-active="${u.is_active ? 1 : 0}" class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${u.is_active ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'}">
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${u.is_active ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"></path>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'}
              </svg>
              ${u.is_active ? 'Deactivate' : 'Activate'}
            </button>
          </div>
        </div>`;
      listEl.appendChild(card);
    });
    
    updateStats(users);
  }

  function load() {
    // Add loading state to refresh button
    const originalText = btnRefresh.innerHTML;
    btnRefresh.innerHTML = `
      <svg class="w-4 h-4 inline mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
      </svg>
      Loading...
    `;
    btnRefresh.disabled = true;
    
    const val = filterRole.value;
    const valid = val && ['admin','cashier','customer'].includes(val) ? val : undefined;
    
    authFetch({ action: 'list_users', filter_role: valid })
      .then(d => {
        render(d.users);
        // Show success feedback briefly
        btnRefresh.innerHTML = `
          <svg class="w-4 h-4 inline mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
          Refreshed!
        `;
        setTimeout(() => {
          btnRefresh.innerHTML = originalText;
          btnRefresh.disabled = false;
        }, 1500);
      })
      .catch(e => {
        console.error('Error loading users:', e);
        alert(e.message);
        // Reset button on error
        btnRefresh.innerHTML = originalText;
        btnRefresh.disabled = false;
      });
  }

  btnRefresh.addEventListener('click', (e) => {
    e.preventDefault();
    load();
  });
  filterRole.addEventListener('change', load);
  btnNew.addEventListener('click', () => { 
    frm.reset(); 
    roleSel.value = 'cashier'; 
    dlg.showModal(); 
  });
  btnCancel.addEventListener('click', () => dlg.close());

  // No second dropdown anymore

  frm.addEventListener('submit', (e) => {
    e.preventDefault();
    const selectedRole = roleSel.value;
    const payload = {
      action: 'create_user',
      first_name: document.getElementById('first_name').value.trim(),
      last_name: document.getElementById('last_name').value.trim(),
      email: document.getElementById('email').value.trim(),
      phone: document.getElementById('phone').value.trim(),
      address: document.getElementById('address').value.trim(),
      role: selectedRole,
      password: document.getElementById('password').value.trim() || undefined
    };
    // No staff_role submitted anymore
    authFetch(payload)
      .then(d => { 
        dlg.close(); 
        load(); 
        if (d.temporary_password) {
          alert(`User created successfully!\n\nTemporary password: ${d.temporary_password}\n\nPlease share this password with the user securely.`);
        } else {
          alert('User created successfully!');
        }
      })
      .catch(e => {
        console.error('Error creating user:', e);
        alert(e.message);
      });
  });

  listEl.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    let id = parseInt(btn.getAttribute('data-id'), 10);
    if (Number.isNaN(id)) {
      const holder = btn.closest('[data-user-id]');
      if (holder) {
        // Strip non-digits just in case
        const raw = holder.getAttribute('data-user-id') || '';
        const digits = raw.match(/\d+/);
        if (digits) id = parseInt(digits[0], 10);
      }
    }
    const act = btn.getAttribute('data-act');
    if (Number.isNaN(id)) {
      alert('Unable to determine user id for this action. Please refresh the page and try again.');
      return;
    }
    
    if (act === 'toggle') {
      const current = parseInt(btn.getAttribute('data-active') || '0', 10);
      const activate = current ? 0 : 1; // toggle
      const action = activate ? 'activate' : 'deactivate';
      
      if (confirm(`Are you sure you want to ${action} this user?`)) {
        authFetch({ action: 'deactivate_user', user_id: id, active: activate })
          .then((res) => {
            // Update button state immediately for snappy UI, then reload
            btn.setAttribute('data-active', String(activate));
            btn.className = `px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 ${activate ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'}`;
            btn.innerHTML = `
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                ${activate ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"></path>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'}
              </svg>
              ${activate ? 'Deactivate' : 'Activate'}
            `;
            alert(`User ${action}d successfully!`);
            load();
          })
          .catch(err => {
            console.error('Error updating user status:', err);
            alert(err.message);
          });
      }
    } else if (act === 'role') {
      const role = prompt('Set role: admin | cashier | customer');
      if (!role || !['admin', 'cashier', 'customer'].includes(role)) return;
      if (confirm(`Change user role to ${role}?`)) {
        authFetch({ action: 'update_user_role', user_id: id, role })
          .then(() => {
            load();
            alert('User role updated successfully!');
          })
          .catch(err => {
            console.error('Error updating user role:', err);
            alert(err.message);
          });
      }
    }
  });

  // Initial load
  load();
})();


