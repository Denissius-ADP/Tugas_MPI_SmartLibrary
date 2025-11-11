const staffRoles = ['kepala_perpustakaan', 'staff'];

const state = {
  user: null,
  stats: null,
  categories: [],
  books: [],
  members: [],
  loans: [],
  logs: [],
};

let toastTimer;
let bookFilters = { q: '', kategori: '', status: '' };

const dom = {
  loginScreen: document.getElementById('login-screen'),
  loginForm: document.getElementById('login-form'),
  logoutBtn: document.getElementById('logout-btn'),
  refreshBtn: document.getElementById('refresh-btn'),
  navLinks: Array.from(document.querySelectorAll('.nav-link')),
  panels: Array.from(document.querySelectorAll('[data-panel]')),
  sidebarUser: document.getElementById('sidebar-user'),
  sidebarRole: document.getElementById('sidebar-role'),
  userName: document.getElementById('user-name'),
  statsCards: document.getElementById('stats-cards'),
  recentLoans: document.getElementById('recent-loans'),
  overdueList: document.getElementById('overdue-list'),
  filterForm: document.getElementById('filter-form'),
  filterCategory: document.getElementById('filter-category'),
  categoryOptions: document.getElementById('category-options'),
  bookCount: document.getElementById('book-count'),
  booksTable: document.querySelector('#books-table tbody'),
  bookForm: document.getElementById('book-form'),
  bookFormTitle: document.getElementById('book-form-title'),
  bookFormReset: document.getElementById('book-form-reset'),
  bookCategory: document.getElementById('book-category'),
  categoryForm: document.getElementById('category-form'),
  categoryFormReset: document.getElementById('category-form-reset'),
  categoryTable: document.querySelector('#category-table tbody'),
  loanForm: document.getElementById('loan-form'),
  loanBook: document.getElementById('loan-book'),
  loanUser: document.getElementById('loan-user'),
  dueDate: document.getElementById('due-date'),
  loansTable: document.querySelector('#loans-table tbody'),
  userForm: document.getElementById('user-form'),
  usersTable: document.querySelector('#users-table tbody'),
  activityFeed: document.getElementById('activity-feed'),
  toast: document.getElementById('toast'),
  loader: document.getElementById('global-loader'),
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'medium',
  timeStyle: 'short',
});

init();

function init() {
  attachEvents();
  setDefaultDueDate();
  bootstrap();
}

function attachEvents() {
  dom.loginForm.addEventListener('submit', handleLogin);
  dom.logoutBtn.addEventListener('click', handleLogout);
  dom.refreshBtn.addEventListener('click', hydrateApp);
  dom.navLinks.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('locked')) return;
      showPanel(btn.dataset.target, btn);
    });
  });

  dom.filterForm.addEventListener('submit', handleFilter);
  dom.bookForm.addEventListener('submit', saveBook);
  dom.bookFormReset.addEventListener('click', resetBookForm);
  dom.categoryForm.addEventListener('submit', saveCategory);
  dom.categoryFormReset.addEventListener('click', resetCategoryForm);
  dom.loanForm.addEventListener('submit', saveLoan);
  dom.userForm.addEventListener('submit', saveUser);

  dom.booksTable.addEventListener('click', handleBookTableAction);
  dom.categoryTable.addEventListener('click', handleCategoryAction);
  dom.loansTable.addEventListener('click', handleLoanAction);
  dom.usersTable.addEventListener('click', handleUserAction);
}

async function bootstrap() {
  toggleLoader(true);
  try {
    const session = await api('auth', { params: { action: 'me' } });
    if (session.authenticated) {
      state.user = session.user;
      dom.loginScreen.classList.remove('visible');
      updateRoleVisibility();
      await hydrateApp();
      showToast(`Selamat datang ${state.user.name}`);
    } else {
      showLogin();
    }
  } catch (error) {
    showToast(error.message || 'Gagal memuat sesi', 'danger');
    showLogin();
  } finally {
    toggleLoader(false);
  }
}

async function hydrateApp() {
  if (!state.user) return;

  toggleLoader(true);
  try {
    const tasks = [loadCategories(), loadBooks()];

    if (staffRoles.includes(state.user.role)) {
      tasks.push(loadDashboard(), loadMembers(), loadLoans(), loadLogs());
    }

    await Promise.all(tasks);
  } catch (error) {
    showToast(error.message || 'Gagal memuat data', 'danger');
  } finally {
    toggleLoader(false);
  }
}

/* ----------------------- API helpers ------------------------------------ */

async function api(entity, { method = 'GET', params = {}, body } = {}) {
  const query = new URLSearchParams({ entity, ...params });
  const response = await fetch(`api.php?${query.toString()}`, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.message || 'Terjadi kesalahan');
  }

  return data;
}

/* ----------------------- Loaders ---------------------------------------- */

async function loadDashboard() {
  const response = await api('dashboard');
  state.stats = response.data;
  renderDashboard();
}

async function loadCategories() {
  const response = await api('categories');
  state.categories = response.data || [];
  renderCategories();
}

async function loadBooks() {
  const response = await api('books', { params: bookFilters });
  state.books = response.data || [];
  renderBooks();
}

async function loadMembers() {
  const response = await api('users', { params: { scope: 'members' } });
  state.members = response.data || [];
  renderMembers();
}

async function loadLoans() {
  const response = await api('loans');
  state.loans = response.data || [];
  renderLoans();
}

async function loadLogs() {
  const response = await api('logs');
  state.logs = response.data || [];
  renderLogs();
}

/* ----------------------- Renderers -------------------------------------- */

function renderDashboard() {
  if (!state.stats) {
    dom.statsCards.innerHTML = '';
    dom.recentLoans.innerHTML = emptyState('Belum ada data');
    dom.overdueList.innerHTML = emptyState('Belum ada data');
    return;
  }

  const totals = state.stats.totals;
  const cards = [
    { label: 'Total Buku', value: totals.books },
    { label: 'Eksemplar Tersedia', value: totals.available_books },
    { label: 'Anggota Aktif', value: totals.members },
    { label: 'Peminjaman Aktif', value: totals.active_loans },
    { label: 'Overdue', value: totals.overdue_loans },
  ];

  dom.statsCards.innerHTML = cards
    .map(
      (card) => `<div class="stat-card">
        <span>${card.label}</span>
        <strong>${card.value ?? 0}</strong>
      </div>`
    )
    .join('');

  dom.recentLoans.innerHTML = state.stats.recent.length
    ? state.stats.recent
        .map(
          (loan) => `<div class="list-item">
            <div>
              <strong>${loan.judul}</strong>
              <p>${loan.peminjam}</p>
            </div>
            <span class="badge ${statusClass(loan.status)}">${loan.status}</span>
          </div>`
        )
        .join('')
    : emptyState('Belum ada transaksi');

  dom.overdueList.innerHTML = state.stats.overdue.length
    ? state.stats.overdue
        .map(
          (loan) => `<div class="list-item overdue">
            <div>
              <strong>${loan.judul}</strong>
              <p>${loan.peminjam}</p>
            </div>
            <div class="stacked">
              <small>Jatuh tempo</small>
              <strong>${formatDate(loan.tanggal_kembali)}</strong>
            </div>
          </div>`
        )
        .join('')
    : emptyState('Tidak ada buku terlambat 🎉');
}

function renderCategories() {
  dom.categoryTable.innerHTML = state.categories.length
    ? state.categories
        .map(
          (cat) => `<tr data-id="${cat.id}">
        <td>${cat.nama}</td>
        <td class="actions">
          <button class="ghost-btn tiny" data-action="edit-category">Edit</button>
          <button class="ghost-btn tiny danger" data-action="delete-category">Hapus</button>
        </td>
      </tr>`
        )
        .join('')
    : `<tr><td colspan="2" class="empty">Belum ada kategori</td></tr>`;

  updateCategoryOptions();
}

function renderBooks() {
  dom.booksTable.innerHTML = state.books.length
    ? state.books
        .map(
          (book) => `<tr data-id="${book.id}">
        <td>
          <div class="stacked">
            <strong>${book.judul}</strong>
            <small>${book.pengarang ?? '-'}</small>
            <small>ISBN: ${book.isbn ?? '-'}</small>
          </div>
        </td>
        <td>${book.kategori ?? '-'}</td>
        <td>${book.penerbit ?? '-'}</td>
        <td>${book.tahun_terbit ?? '-'}</td>
        <td><span class="pill ${book.jumlah_stok > 0 ? 'success' : 'danger'}">${book.jumlah_stok}</span></td>
        <td>${book.lokasi_rak ?? '-'}</td>
        <td class="actions">
          <button class="ghost-btn tiny" data-action="edit-book">Edit</button>
          <button class="ghost-btn tiny danger" data-action="delete-book">Hapus</button>
        </td>
      </tr>`
        )
        .join('')
    : `<tr><td colspan="7" class="empty">Belum ada buku</td></tr>`;

  dom.bookCount.textContent = `${state.books.length} buku`;
  updateLoanBookOptions();
}

function renderLoans() {
  dom.loansTable.innerHTML = state.loans.length
    ? state.loans
        .map((loan) => {
          const overdue =
            loan.status === 'dipinjam' &&
            loan.tanggal_kembali &&
            new Date(loan.tanggal_kembali) < new Date();
          return `<tr data-id="${loan.id}">
            <td>${loan.peminjam}</td>
            <td>${loan.buku}</td>
            <td>${formatDate(loan.tanggal_pinjam)}</td>
            <td>${formatDate(loan.tanggal_kembali)}</td>
            <td>
              <span class="badge ${overdue ? 'danger' : statusClass(loan.status)}">
                ${overdue ? 'overdue' : loan.status}
              </span>
            </td>
            <td class="actions">
              ${
                loan.status === 'dipinjam'
                  ? `<button class="ghost-btn tiny" data-action="return-loan">Kembalikan</button>`
                  : ''
              }
              <button class="ghost-btn tiny danger" data-action="delete-loan">Hapus</button>
            </td>
          </tr>`;
        })
        .join('')
    : `<tr><td colspan="6" class="empty">Belum ada peminjaman</td></tr>`;
}

function renderMembers() {
  const canManage = state.user?.role === 'kepala_perpustakaan';
  dom.usersTable.innerHTML = state.members.length
    ? state.members
        .map(
          (member) => `<tr data-id="${member.id}">
        <td>
          <div class="stacked">
            <strong>${member.nama_lengkap}</strong>
            <small>${member.email ?? '-'}</small>
          </div>
        </td>
        <td>${member.username}</td>
        <td>
          <div class="stacked">
            <small>Telp: ${member.no_telp ?? '-'}</small>
            <small>${member.alamat ?? '-'}</small>
          </div>
        </td>
        <td>${member.role}</td>
        <td class="actions">
          ${
            canManage
              ? `<button class="ghost-btn tiny danger" data-action="delete-user">Hapus</button>`
              : ''
          }
        </td>
      </tr>`
        )
        .join('')
    : `<tr><td colspan="5" class="empty">Belum ada anggota</td></tr>`;

  updateLoanUserOptions();
}

function renderLogs() {
  dom.activityFeed.innerHTML = state.logs.length
    ? state.logs
        .map(
          (log) => `<div class="activity-item">
        <div>
          <strong>${log.actor ?? 'Sistem'}</strong>
          <p>${log.aktivitas}</p>
        </div>
        <small>${formatDateTime(log.created_at)}</small>
      </div>`
        )
        .join('')
    : emptyState('Belum ada aktivitas');
}

/* ----------------------- Actions ---------------------------------------- */

async function handleLogin(event) {
  event.preventDefault();
  const formData = new FormData(dom.loginForm);
  const payload = {
    credential: formData.get('credential'),
    password: formData.get('password'),
  };

  try {
    const response = await api('auth', {
      method: 'POST',
      params: { action: 'login' },
      body: payload,
    });

    state.user = response.user;
    dom.loginScreen.classList.remove('visible');
    updateRoleVisibility();
    await hydrateApp();
    showToast('Login berhasil');
  } catch (error) {
    showToast(error.message || 'Login gagal', 'danger');
  }
}

async function handleLogout() {
  try {
    await api('auth', { method: 'DELETE', params: { action: 'logout' } });
  } catch (error) {
    console.error(error);
  } finally {
    Object.assign(state, {
      user: null,
      stats: null,
      categories: [],
      books: [],
      members: [],
      loans: [],
      logs: [],
    });
    dom.loginScreen.classList.add('visible');
    updateRoleVisibility();
    dom.statsCards.innerHTML = '';
  }
}

function handleFilter(event) {
  event.preventDefault();
  const formData = new FormData(dom.filterForm);
  bookFilters = {
    q: formData.get('q') || '',
    kategori: formData.get('kategori') || '',
    status: formData.get('status') || '',
  };
  loadBooks();
}

async function saveBook(event) {
  event.preventDefault();
  const formData = new FormData(dom.bookForm);
  const id = formData.get('id');
  const payload = {
    judul: formData.get('judul'),
    pengarang: formData.get('pengarang'),
    penerbit: formData.get('penerbit') || null,
    tahun_terbit: formData.get('tahun_terbit') || null,
    isbn: formData.get('isbn') || null,
    kategori: formData.get('kategori'),
    jumlah_stok: Number(formData.get('jumlah_stok')) || 0,
    lokasi_rak: formData.get('lokasi_rak') || null,
  };

  try {
    await api('books', {
      method: id ? 'PUT' : 'POST',
      params: id ? { id } : {},
      body: payload,
    });
    showToast(id ? 'Buku diperbarui' : 'Buku ditambahkan');
    resetBookForm();
    await Promise.all([loadBooks(), staffRoles.includes(state.user.role) ? loadLoans() : Promise.resolve()]);
  } catch (error) {
    showToast(error.message, 'danger');
  }
}

function resetBookForm() {
  dom.bookForm.reset();
  document.getElementById('book-id').value = '';
  dom.bookFormTitle.textContent = 'Tambah / Edit Buku';
  const stockInput = dom.bookForm.querySelector('input[name="jumlah_stok"]');
  if (stockInput) {
    stockInput.value = 1;
  }
}

async function saveCategory(event) {
  event.preventDefault();
  const formData = new FormData(dom.categoryForm);
  const id = formData.get('id');
  const payload = {
    nama: formData.get('nama'),
    deskripsi: formData.get('deskripsi') || null,
  };

  try {
    await api('categories', {
      method: id ? 'PUT' : 'POST',
      params: id ? { id } : {},
      body: payload,
    });
    showToast('Kategori tersimpan');
    resetCategoryForm();
    await loadCategories();
  } catch (error) {
    showToast(error.message, 'danger');
  }
}

function resetCategoryForm() {
  dom.categoryForm.reset();
  document.getElementById('category-id').value = '';
}

async function saveLoan(event) {
  event.preventDefault();
  const formData = new FormData(dom.loanForm);
  const payload = {
    user_id: Number(formData.get('user_id')),
    buku_id: Number(formData.get('buku_id')),
    due_date: formData.get('due_date'),
  };

  try {
    await api('loans', { method: 'POST', body: payload });
    showToast('Peminjaman dicatat');
    dom.loanForm.reset();
    setDefaultDueDate();
    await Promise.all([loadLoans(), loadBooks(), loadDashboard()]);
  } catch (error) {
    showToast(error.message, 'danger');
  }
}

async function saveUser(event) {
  event.preventDefault();
  const formData = new FormData(dom.userForm);
  const payload = {
    username: formData.get('username'),
    nama_lengkap: formData.get('nama_lengkap'),
    email: formData.get('email') || null,
    no_telp: formData.get('no_telp') || null,
    alamat: formData.get('alamat') || null,
    password: formData.get('password'),
    role: formData.get('role') || 'anggota',
  };

  if (state.user.role !== 'kepala_perpustakaan') {
    payload.role = 'anggota';
  }

  try {
    await api('users', { method: 'POST', body: payload });
    showToast('Anggota ditambahkan');
    dom.userForm.reset();
    await loadMembers();
  } catch (error) {
    showToast(error.message, 'danger');
  }
}

async function handleBookTableAction(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  const row = button.closest('tr');
  const id = row?.dataset.id;
  if (!id) return;

  if (button.dataset.action === 'edit-book') {
    const book = state.books.find((item) => String(item.id) === id);
    if (!book) return;
    dom.bookFormTitle.textContent = `Edit ${book.judul}`;
    document.getElementById('book-id').value = book.id;
    dom.bookForm.judul.value = book.judul;
    dom.bookForm.pengarang.value = book.pengarang ?? '';
    dom.bookForm.penerbit.value = book.penerbit ?? '';
    dom.bookForm.tahun_terbit.value = book.tahun_terbit ?? '';
    dom.bookForm.kategori.value = book.kategori ?? '';
    dom.bookForm.isbn.value = book.isbn ?? '';
    dom.bookForm.jumlah_stok.value = book.jumlah_stok ?? 0;
    dom.bookForm.lokasi_rak.value = book.lokasi_rak ?? '';
  }

  if (button.dataset.action === 'delete-book') {
    if (!confirm('Hapus buku ini?')) return;
    try {
      await api('books', { method: 'DELETE', params: { id } });
      showToast('Buku dihapus');
      await loadBooks();
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }
}

async function handleCategoryAction(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  const row = button.closest('tr');
  const id = row?.dataset.id;

  if (button.dataset.action === 'edit-category') {
    const category = state.categories.find((item) => String(item.id) === id);
    if (!category) return;
    document.getElementById('category-id').value = category.id;
    dom.categoryForm.nama.value = category.nama;
    dom.categoryForm.deskripsi.value = category.deskripsi ?? '';
  }

  if (button.dataset.action === 'delete-category') {
    if (!confirm('Hapus kategori ini?')) return;
    try {
      await api('categories', { method: 'DELETE', params: { id } });
      showToast('Kategori dihapus');
      await loadCategories();
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }
}

async function handleLoanAction(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  const row = button.closest('tr');
  const id = row?.dataset.id;

  if (button.dataset.action === 'return-loan') {
    try {
      await api('loans', { method: 'PATCH', params: { id }, body: { action: 'return' } });
      showToast('Buku dikembalikan');
      await Promise.all([loadLoans(), loadBooks(), loadDashboard()]);
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }

  if (button.dataset.action === 'delete-loan') {
    if (!confirm('Hapus transaksi ini?')) return;
    try {
      await api('loans', { method: 'DELETE', params: { id } });
      showToast('Peminjaman dihapus');
      await Promise.all([loadLoans(), loadBooks(), loadDashboard()]);
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }
}

async function handleUserAction(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;
  const row = button.closest('tr');
  const id = row?.dataset.id;

  if (button.dataset.action === 'delete-user') {
    if (!confirm('Hapus akun ini?')) return;
    try {
      await api('users', { method: 'DELETE', params: { id } });
      showToast('Akun dihapus');
      await loadMembers();
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }
}

/* ----------------------- Helpers ---------------------------------------- */

function updateCategoryOptions() {
  const options = state.categories
    .map((cat) => `<option value="${cat.nama}">${cat.nama}</option>`)
    .join('');

  if (dom.categoryOptions) {
    dom.categoryOptions.innerHTML = options;
  }

  if (dom.filterCategory) {
    dom.filterCategory.innerHTML = `<option value="">Semua kategori</option>${options}`;
  }
}

function updateLoanBookOptions() {
  if (!dom.loanBook) return;
  dom.loanBook.innerHTML = state.books
    .filter((book) => book.jumlah_stok > 0)
    .map((book) => `<option value="${book.id}">${book.judul} (${book.jumlah_stok} tersedia)</option>`)
    .join('');
}

function updateLoanUserOptions() {
  if (!dom.loanUser) return;
  dom.loanUser.innerHTML = state.members
    .map((member) => `<option value="${member.id}">${member.nama_lengkap}</option>`)
    .join('');
}

function statusClass(status) {
  if (status === 'dikembalikan') return 'success';
  if (status === 'dipinjam') return 'warning';
  return 'danger';
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date) ? '-' : dateFormatter.format(date);
}

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date) ? '-' : dateTimeFormatter.format(date);
}

function emptyState(text) {
  return `<p class="empty">${text}</p>`;
}

function showPanel(id, trigger) {
  dom.navLinks.forEach((btn) => btn.classList.toggle('active', btn === trigger));
  dom.panels.forEach((panel) => {
    if (panel.id === id) {
      panel.classList.remove('hidden');
    } else {
      panel.classList.add('hidden');
    }
  });
}

function updateRoleVisibility() {
  const role = state.user?.role || 'guest';
  dom.sidebarUser.textContent = state.user ? state.user.name : 'Belum login';
  dom.sidebarRole.textContent = state.user ? role : '-';
  dom.userName.textContent = state.user ? state.user.name : 'silakan login';

  document.querySelectorAll('[data-role="staff"]').forEach((el) => {
    el.classList.toggle('locked', !staffRoles.includes(role));
  });

  document.querySelectorAll('[data-role="head"]').forEach((el) => {
    el.classList.toggle('locked', role !== 'kepala_perpustakaan');
  });

  dom.navLinks
    .filter((btn) => btn.classList.contains('active') && btn.classList.contains('locked'))
    .forEach(() => {
      const first = dom.navLinks.find((btn) => !btn.classList.contains('locked'));
      if (first) {
        showPanel(first.dataset.target, first);
      }
    });
}

function showLogin() {
  dom.loginScreen.classList.add('visible');
}

function showToast(message, variant = 'success') {
  dom.toast.textContent = message;
  dom.toast.className = `toast ${variant} visible`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => dom.toast.classList.remove('visible'), 3000);
}

function toggleLoader(show) {
  dom.loader.classList.toggle('hidden', !show);
}

function setDefaultDueDate() {
  if (!dom.dueDate) return;
  const date = new Date();
  date.setDate(date.getDate() + 7);
  dom.dueDate.value = date.toISOString().slice(0, 10);
}
