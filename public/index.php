<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Smart Library Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/styles.css" />
  </head>
  <body>
    <div id="global-loader" class="global-loader hidden">
      <div class="loader-dot"></div>
      <p>Memuat...</p>
    </div>

    <div class="app-shell">
      <aside class="sidebar">
        <div class="brand">
          <span class="logo">SL</span>
          <div>
            <p class="eyebrow">Perpustakaan</p>
            <h1>Smart Library</h1>
          </div>
        </div>
        <nav class="menu">
          <button class="nav-link active" data-target="dashboard-panel">Dashboard</button>
          <button class="nav-link" data-target="catalog-panel">Koleksi</button>
          <button class="nav-link" data-target="loans-panel" data-role="staff">Peminjaman</button>
          <button class="nav-link" data-target="users-panel" data-role="staff">Users</button>
          <button class="nav-link" data-target="activity-panel" data-role="staff">Aktivitas</button>
        </nav>
        <div class="sidebar-foot">
          <p id="sidebar-user">Belum login</p>
          <small id="sidebar-role">-</small>
        </div>
      </aside>

      <main class="content">
        <header class="top-bar">
          <div>
            <p class="eyebrow">Hai,</p>
            <h2 id="user-name">silakan login</h2>
          </div>
          <div class="top-actions">
            <button id="refresh-btn" class="ghost-btn">Refresh data</button>
            <button id="logout-btn" class="solid-btn danger">Logout</button>
          </div>
        </header>

        <section id="dashboard-panel" data-panel class="panel">
          <div class="panel-header">
            <div>
              <h3>Status Koleksi</h3>
              <p>Cek ringkasan koleksi, peminjaman aktif, dan keterlambatan.</p>
            </div>
          </div>
          <div id="stats-cards" class="card-grid"></div>
          <div class="grid-2 stretch">
            <div class="card">
              <div class="card-header">
                <h4>Aktivitas Peminjaman Terbaru</h4>
              </div>
              <div id="recent-loans" class="list"></div>
            </div>
            <div class="card">
              <div class="card-header">
                <h4>Butuh Perhatian (Overdue)</h4>
              </div>
              <div id="overdue-list" class="list"></div>
            </div>
          </div>
        </section>

        <section id="catalog-panel" data-panel class="panel hidden">
          <div class="panel-header">
            <div>
              <h3>Katalog Buku</h3>
              <p>Filter buku, update stok, dan kelola kategori.</p>
            </div>
            <form id="filter-form" class="filter-form">
              <input type="text" name="q" placeholder="Cari judul / pengarang / ISBN" />
              <select name="kategori" id="filter-category">
                <option value="">Semua kategori</option>
              </select>
              <select name="status">
                <option value="">Semua stok</option>
                <option value="available">Tersedia</option>
                <option value="empty">Kosong</option>
              </select>
              <button type="submit" class="solid-btn small">Cari</button>
            </form>
          </div>

          <div class="grid-2 stretch">
            <div class="card">
              <div class="card-header">
                <h4>Daftar Buku</h4>
                <span id="book-count" class="pill"></span>
              </div>
              <div class="table-wrapper">
                <table id="books-table">
                  <thead>
                    <tr>
                      <th>Judul & Pengarang</th>
                      <th>Kategori</th>
                      <th>Penerbit</th>
                      <th>Tahun</th>
                      <th>Stok</th>
                      <th>Lokasi</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <div class="stack">
              <div class="card">
                <div class="card-header">
                  <h4 id="book-form-title">Tambah / Edit Buku</h4>
                  <button type="button" id="book-form-reset" class="ghost-btn small">Reset</button>
                </div>
                <form id="book-form" class="form-grid">
                  <input type="hidden" name="id" id="book-id" />
                  <label>
                    Judul
                    <input type="text" name="judul" required />
                  </label>
                  <label>
                    Pengarang
                    <input type="text" name="pengarang" required />
                  </label>
                  <label>
                    Penerbit
                    <input type="text" name="penerbit" />
                  </label>
                  <label>
                    Tahun Terbit
                    <input type="number" name="tahun_terbit" min="1900" max="2099" />
                  </label>
                  <label>
                    Kategori
                    <input type="text" name="kategori" id="book-category" list="category-options" placeholder="Pilih atau ketik kategori" required />
                    <datalist id="category-options"></datalist>
                  </label>
                  <label>
                    ISBN
                    <input type="text" name="isbn" />
                  </label>
                  <label>
                    Jumlah Stok
                    <input type="number" name="jumlah_stok" min="0" value="1" required />
                  </label>
                  <label>
                    Lokasi Rak
                    <input type="text" name="lokasi_rak" />
                  </label>
                  <div class="form-actions">
                    <button type="submit" class="solid-btn">Simpan Buku</button>
                  </div>
                </form>
              </div>

              <div class="card">
                <div class="card-header">
                  <h4>Kategori Buku</h4>
                  <button type="button" id="category-form-reset" class="ghost-btn small">Reset</button>
                </div>
                <form id="category-form" class="form-grid compact">
                  <input type="hidden" name="id" id="category-id" />
                  <label>
                    Nama kategori
                    <input type="text" name="nama" required />
                  </label>
                  <label>
                    Deskripsi
                    <textarea name="deskripsi" rows="2"></textarea>
                  </label>
                  <div class="form-actions">
                    <button type="submit" class="solid-btn small">Simpan Kategori</button>
                  </div>
                </form>
                <div class="table-wrapper small">
                  <table id="category-table">
                    <thead>
                      <tr>
                        <th>Nama</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="loans-panel" data-panel data-role="staff" class="panel hidden">
          <div class="panel-header">
            <div>
              <h3>Peminjaman</h3>
              <p>Catat peminjaman baru dan proses pengembalian.</p>
            </div>
          </div>
          <div class="grid-2 stretch">
            <div class="card">
              <div class="card-header">
                <h4>Form Peminjaman</h4>
              </div>
              <form id="loan-form" class="form-grid">
                <label>
                  Anggota
                  <select name="user_id" id="loan-user" required></select>
                </label>
                <label>
                  Buku
                  <select name="buku_id" id="loan-book" required></select>
                </label>
                <label>
                  Jatuh tempo
                  <input type="date" name="due_date" id="due-date" required />
                </label>
                <div class="form-actions">
                  <button type="submit" class="solid-btn">Catat Peminjaman</button>
                </div>
              </form>
            </div>

            <div class="card">
              <div class="card-header">
                <h4>Daftar Peminjaman</h4>
              </div>
              <div class="table-wrapper">
                <table id="loans-table">
                  <thead>
                    <tr>
                      <th>Anggota</th>
                      <th>Buku</th>
                      <th>Pinjam</th>
                      <th>Jatuh Tempo</th>
                      <th>Status</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <section id="users-panel" data-panel data-role="staff" class="panel hidden">
          <div class="panel-header">
            <div>
              <h3>Anggota</h3>
              <p>Registrasi anggota baru dan pantau status akun.</p>
            </div>
          </div>
          <div class="grid-2 stretch">
            <div class="card">
              <div class="card-header">
                <h4>Tambah Anggota</h4>
              </div>
              <form id="user-form" class="form-grid">
                <label>Username
                  <input type="text" name="username" required />
                </label>
                <label>Nama lengkap
                  <input type="text" name="nama_lengkap" required />
                </label>
                <label>Email
                  <input type="email" name="email" />
                </label>
                <label>No. Telepon
                  <input type="tel" name="no_telp" />
                </label>
                <label>Alamat
                  <textarea name="alamat" rows="3"></textarea>
                </label>
                <label>Password awal
                  <input type="password" name="password" required />
                </label>
                <label data-role="head">
                  Peran
                  <select name="role">
                    <option value="anggota" selected>Anggota</option>
                    <option value="staff">Staff</option>
                    <option value="kepala_perpustakaan">Kepala Perpustakaan</option>
                  </select>
                </label>
                <div class="form-actions">
                  <button type="submit" class="solid-btn">Simpan Anggota</button>
                </div>
              </form>
            </div>

            <div class="card">
              <div class="card-header">
                <h4>Daftar Anggota</h4>
              </div>
              <div class="table-wrapper">
                <table id="users-table">
                  <thead>
                    <tr>
                      <th>Nama</th>
                      <th>Username</th>
                      <th>Kontak</th>
                      <th>Peran</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <section id="activity-panel" data-panel data-role="staff" class="panel hidden">
          <div class="panel-header">
            <div>
              <h3>Log Aktivitas</h3>
              <p>Semua aksi penting dicatat otomatis.</p>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <h4>Riwayat Terbaru</h4>
            </div>
            <div id="activity-feed" class="activity-feed"></div>
          </div>
        </section>
      </main>
    </div>

    <div id="login-screen" class="login-screen visible">
      <div class="login-card">
        <div class="login-brand">
          <span class="logo">SL</span>
          <div>
            <p class="eyebrow">Smart Library</p>
            <h2>Masuk Dashboard</h2>
          </div>
        </div>
        <form id="login-form">
          <label>Email / Username
            <input type="text" name="credential" placeholder="kepalalib" required />
          </label>
          <label>Password
            <input type="password" name="password" placeholder="••••••••" required />
          </label>
          <button type="submit" class="solid-btn full">Masuk</button>
          <p class="hint">Akun awal: kepalalib / admin123</p>
        </form>
      </div>
    </div>

    <div id="toast" class="toast hidden"></div>

    <script type="module" src="assets/app.js"></script>
  </body>
</html>
