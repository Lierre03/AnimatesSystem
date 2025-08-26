const tabAdmin = document.getElementById('tab-admin');
const tabStaff = document.getElementById('tab-staff');
const expectedRoleInput = document.getElementById('expectedRole');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const form = document.getElementById('signinForm');
const errorEl = document.getElementById('error');
const togglePasswordBtn = document.getElementById('togglePassword');

function setRole(role) {
  expectedRoleInput.value = role;
  if (role === 'admin') {
    tabAdmin.classList.remove('tab-inactive');
    tabAdmin.classList.add('tab-active');
    tabStaff.classList.remove('tab-active');
    tabStaff.classList.add('tab-inactive');
    emailInput.placeholder = 'admin@animates.ph';
  } else {
    tabStaff.classList.remove('tab-inactive');
    tabStaff.classList.add('tab-active');
    tabAdmin.classList.remove('tab-active');
    tabAdmin.classList.add('tab-inactive');
    emailInput.placeholder = 'staff@animates.ph';
  }
  errorEl.classList.add('hidden');
  errorEl.textContent = '';
  emailInput.focus();
}

tabAdmin.addEventListener('click', () => setRole('admin'));
tabStaff.addEventListener('click', () => setRole('staff'));
setRole('admin');

togglePasswordBtn.addEventListener('click', () => {
  const isPwd = passwordInput.type === 'password';
  passwordInput.type = isPwd ? 'text' : 'password';
  
  // Update the eye icon
  const icon = togglePasswordBtn.querySelector('svg');
  if (isPwd) {
    // Show password - change to eye-off icon
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>';
  } else {
    // Hide password - change to eye icon
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
  }
});

async function signin(email, password) {
  const res = await fetch('http://localhost/animates/api/auth.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'login', email, password })
  });
  const clone = res.clone();
  let data;
  try { data = await res.json(); } catch (_) { /* not JSON */ }
  if (!res.ok || !data || data.success === false) {
    let message = (data && (data.error || data.message)) || '';
    if (!message) {
      try { message = await clone.text(); } catch (e) { /* ignore */ }
    }
    throw new Error(message || `HTTP ${res.status}`);
  }
  return data;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  errorEl.classList.add('hidden');
  errorEl.textContent = '';
  const email = emailInput.value.trim();
  const password = passwordInput.value;
  const expectedRole = expectedRoleInput.value;
  try {
    const result = await signin(email, password);
    const actualRole = (result && result.user && result.user.role) ? result.user.role : '';
    if (!actualRole) throw new Error('Unable to determine user role');
    if ((expectedRole === 'admin' && actualRole !== 'admin') || (expectedRole === 'staff' && actualRole !== 'staff')) {
      throw new Error(`Access denied. ${expectedRole.charAt(0).toUpperCase() + expectedRole.slice(1)} role required.`);
    }
    if (result.token) {
      localStorage.setItem('auth_token', result.token);
      localStorage.setItem('auth_role', actualRole);
      localStorage.setItem('auth_email', result.user.email);
      // Store staff_role if available
      if (result.user.staff_role) {
        localStorage.setItem('auth_staff_role', result.user.staff_role);
      }
      // Store user ID
      if (result.user_id) {
        localStorage.setItem('auth_user_id', result.user_id);
      }
    }
    if (actualRole === 'admin') {
      window.location.href = 'admin_accounts.html';
    } else if (actualRole === 'staff') {
      // Check specific staff role for proper redirection
      const staffRole = (result && result.user && result.user.staff_role) ? result.user.staff_role : '';
      if (staffRole === 'cashier') {
        window.location.href = 'billing_management.html';
      } else {
        window.location.href = 'staff_dashboard.html';
      }
    } else {
      throw new Error('Unsupported role');
    }
  } catch (err) {
    errorEl.textContent = err.message || 'Sign-in error';
    errorEl.classList.remove('hidden');
  }
});


