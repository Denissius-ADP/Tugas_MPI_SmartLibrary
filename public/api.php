<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';

const ROLE_HEAD = 'kepala_perpustakaan';
const ROLE_STAFF = 'staff';
const ROLE_MEMBER = 'anggota';

bootstrapJson();

$entity = $_GET['entity'] ?? '';

if ($entity === '') {
    fail('Parameter entity wajib diisi', 400);
}

switch ($entity) {
    case 'auth':
        handleAuth();
        break;
    case 'categories':
        handleCategories();
        break;
    case 'books':
        handleBooks();
        break;
    case 'users':
        handleUsers();
        break;
    case 'loans':
        handleLoans();
        break;
    case 'dashboard':
        handleDashboard();
        break;
    case 'logs':
        handleLogs();
        break;
    default:
        fail('Endpoint tidak ditemukan', 404);
}

/* --- Handlers ------------------------------------------------------------ */

function handleAuth(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';

    if ($method === 'POST' && $action === 'login') {
        $payload = jsonBody();

        $identifier = trim((string) ($payload['credential'] ?? $payload['email'] ?? ''));
        $password = $payload['password'] ?? '';

        if ($identifier === '' || $password === '') {
            fail('Email / username dan password wajib diisi', 422);
        }

        $stmt = db()->prepare(
            'SELECT id, username, nama_lengkap, role, email, password FROM users WHERE username = :identifier OR email = :identifier LIMIT 1'
        );
        $stmt->execute([':identifier' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            fail('Kredensial tidak valid', 401);
        }

        storeSession($user);
        logAction((int) $user['id'], sprintf('Login oleh %s', $user['username']));

        success(['message' => 'Login berhasil', 'user' => currentUser()]);
    }

    if ($method === 'DELETE' && $action === 'logout') {
        $user = currentUser();
        if ($user) {
            logAction($user['id'], sprintf('Logout oleh %s', $user['username'] ?? $user['name']));
        }

        clearSession();
        success(['message' => 'Logout berhasil']);
    }

    if ($method === 'GET' && $action === 'me') {
        $user = currentUser();
        success(['authenticated' => (bool) $user, 'user' => $user]);
    }

    fail('Endpoint auth tidak dikenali', 404);
}

function handleCategories(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = requireAuth();

    if ($method === 'GET') {
        $stmt = db()->query('SELECT id, nama_kategori AS nama, deskripsi FROM kategori_buku ORDER BY nama_kategori ASC');
        success(['data' => $stmt->fetchAll()]);
    }

    if (!isStaff($user)) {
        fail('Hanya kepala perpustakaan / staff yang boleh mengelola kategori', 403);
    }

    if ($method === 'POST') {
        $payload = jsonBody();

        ensure($payload, [
            'nama' => 'Nama kategori wajib diisi',
        ]);

        $stmt = db()->prepare('INSERT INTO kategori_buku (nama_kategori, deskripsi) VALUES (:nama, :deskripsi)');
        $stmt->execute([
            ':nama' => $payload['nama'],
            ':deskripsi' => $payload['deskripsi'] ?? null,
        ]);

        $id = (int) db()->lastInsertId();
        logAction($user['id'], "Tambah kategori {$payload['nama']} (#{$id})");

        success(['message' => 'Kategori ditambahkan', 'data' => ['id' => $id]]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $id = intParam('id');

        if (!$id) {
            fail('ID kategori wajib diisi', 422);
        }

        $payload = jsonBody();

        ensure($payload, [
            'nama' => 'Nama kategori wajib diisi',
        ]);

        $stmt = db()->prepare('UPDATE kategori_buku SET nama_kategori = :nama, deskripsi = :deskripsi WHERE id = :id');
        $stmt->execute([
            ':nama' => $payload['nama'],
            ':deskripsi' => $payload['deskripsi'] ?? null,
            ':id' => $id,
        ]);

        logAction($user['id'], "Ubah kategori #{$id} menjadi {$payload['nama']}");

        success(['message' => 'Kategori diperbarui', 'data' => ['id' => $id]]);
    }

    if ($method === 'DELETE') {
        $id = intParam('id');

        if (!$id) {
            fail('ID kategori wajib diisi', 422);
        }

        try {
            $stmt = db()->prepare('DELETE FROM kategori_buku WHERE id = :id');
            $stmt->execute([':id' => $id]);
        } catch (PDOException $exception) {
            fail('Kategori sedang dipakai oleh buku', 409);
        }

        logAction($user['id'], "Hapus kategori #{$id}");

        success(['message' => 'Kategori dihapus']);
    }

    fail('Metode tidak didukung', 405);
}

function handleBooks(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = requireAuth();

    if ($method === 'GET') {
        [$limit, $offset] = paginate(30);

        $sql = 'SELECT id, judul, pengarang, penerbit, tahun_terbit, isbn, kategori, jumlah_stok, lokasi_rak, created_at
                FROM buku
                WHERE 1=1';
        $params = [];

        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (judul LIKE :search OR pengarang LIKE :search OR penerbit LIKE :search OR isbn LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $categoryName = trim((string) ($_GET['kategori'] ?? ''));
        if ($categoryName !== '') {
            $sql .= ' AND kategori = :kategori';
            $params[':kategori'] = $categoryName;
        }

        $status = $_GET['status'] ?? '';
        if ($status === 'available') {
            $sql .= ' AND jumlah_stok > 0';
        } elseif ($status === 'empty') {
            $sql .= ' AND jumlah_stok = 0';
        }

        $sql .= ' ORDER BY judul ASC LIMIT :limit OFFSET :offset';

        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        success(['data' => $stmt->fetchAll()]);
    }

    if (!isStaff($user)) {
        fail('Hanya kepala perpustakaan / staff yang dapat mengelola buku', 403);
    }

    if ($method === 'POST') {
        $payload = jsonBody();

        ensure($payload, [
            'judul' => 'Judul wajib diisi',
            'pengarang' => 'Pengarang wajib diisi',
            'kategori' => 'Kategori wajib diisi',
            'jumlah_stok' => 'Jumlah stok wajib diisi',
        ]);

        $stmt = db()->prepare('INSERT INTO buku (judul, pengarang, penerbit, tahun_terbit, isbn, kategori, jumlah_stok, lokasi_rak)
                               VALUES (:judul, :pengarang, :penerbit, :tahun_terbit, :isbn, :kategori, :jumlah_stok, :lokasi_rak)');
        $stmt->execute([
            ':judul' => $payload['judul'],
            ':pengarang' => $payload['pengarang'],
            ':penerbit' => $payload['penerbit'] ?? null,
            ':tahun_terbit' => sanitizeYear($payload['tahun_terbit'] ?? null),
            ':isbn' => $payload['isbn'] ?? null,
            ':kategori' => $payload['kategori'],
            ':jumlah_stok' => max(0, (int) $payload['jumlah_stok']),
            ':lokasi_rak' => $payload['lokasi_rak'] ?? null,
        ]);

        $id = (int) db()->lastInsertId();

        logAction($user['id'], "Tambah buku {$payload['judul']} (#{$id})");

        success(['message' => 'Buku ditambahkan', 'data' => ['id' => $id]]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        $id = intParam('id');

        if (!$id) {
            fail('ID buku wajib diisi', 422);
        }

        $payload = jsonBody();
        ensure($payload, [
            'judul' => 'Judul wajib diisi',
            'pengarang' => 'Pengarang wajib diisi',
            'kategori' => 'Kategori wajib diisi',
            'jumlah_stok' => 'Jumlah stok wajib diisi',
        ]);

        $stmt = db()->prepare('UPDATE buku
                               SET judul = :judul,
                                   pengarang = :pengarang,
                                   penerbit = :penerbit,
                                   tahun_terbit = :tahun_terbit,
                                   isbn = :isbn,
                                   kategori = :kategori,
                                   jumlah_stok = :jumlah_stok,
                                   lokasi_rak = :lokasi_rak
                               WHERE id = :id');
        $stmt->execute([
            ':judul' => $payload['judul'],
            ':pengarang' => $payload['pengarang'],
            ':penerbit' => $payload['penerbit'] ?? null,
            ':tahun_terbit' => sanitizeYear($payload['tahun_terbit'] ?? null),
            ':isbn' => $payload['isbn'] ?? null,
            ':kategori' => $payload['kategori'],
            ':jumlah_stok' => max(0, (int) $payload['jumlah_stok']),
            ':lokasi_rak' => $payload['lokasi_rak'] ?? null,
            ':id' => $id,
        ]);

        logAction($user['id'], "Perbarui buku #{$id}");

        success(['message' => 'Buku diperbarui', 'data' => ['id' => $id]]);
    }

    if ($method === 'DELETE') {
        $id = intParam('id');

        if (!$id) {
            fail('ID buku wajib diisi', 422);
        }

        $activeLoans = db()->prepare("SELECT COUNT(*) FROM peminjaman WHERE buku_id = :id AND status = 'dipinjam'");
        $activeLoans->execute([':id' => $id]);

        if ((int) $activeLoans->fetchColumn() > 0) {
            fail('Buku masih dipinjam, tidak dapat dihapus', 409);
        }

        $stmt = db()->prepare('DELETE FROM buku WHERE id = :id');
        $stmt->execute([':id' => $id]);

        logAction($user['id'], "Hapus buku #{$id}");

        success(['message' => 'Buku dihapus']);
    }

    fail('Metode tidak didukung', 405);
}

function handleUsers(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = requireAuth();

    $scope = $_GET['scope'] ?? 'all';

    if ($method === 'GET' && $scope === 'members') {
        if (!isStaff($user)) {
            fail('Akses modul anggota ditolak', 403);
        }

        $stmt = db()->prepare("SELECT id, username, nama_lengkap, email, no_telp, alamat, role, created_at FROM users WHERE role = :role ORDER BY nama_lengkap ASC");
        $stmt->execute([':role' => ROLE_MEMBER]);
        success(['data' => $stmt->fetchAll()]);
    }

    if ($method === 'GET') {
        if (!isHead($user)) {
            fail('Hanya kepala perpustakaan yang dapat melihat seluruh user', 403);
        }

        $stmt = db()->query('SELECT id, username, nama_lengkap, email, no_telp, alamat, role, created_at FROM users ORDER BY created_at DESC');
        success(['data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        if (!isStaff($user)) {
            fail('Tidak memiliki akses membuat akun baru', 403);
        }

        $payload = jsonBody();
        ensure($payload, [
            'username' => 'Username wajib diisi',
            'nama_lengkap' => 'Nama wajib diisi',
            'password' => 'Password wajib diisi',
        ]);

        $role = $payload['role'] ?? ROLE_MEMBER;
        if (!isHead($user)) {
            $role = ROLE_MEMBER;
        }

        $stmt = db()->prepare('INSERT INTO users (username, password, nama_lengkap, role, email, no_telp, alamat)
                               VALUES (:username, :password, :nama_lengkap, :role, :email, :no_telp, :alamat)');

        try {
            $stmt->execute([
                ':username' => strtolower($payload['username']),
                ':password' => password_hash($payload['password'], PASSWORD_BCRYPT),
                ':nama_lengkap' => $payload['nama_lengkap'],
                ':role' => $role,
                ':email' => $payload['email'] ?? null,
                ':no_telp' => $payload['no_telp'] ?? null,
                ':alamat' => $payload['alamat'] ?? null,
            ]);
        } catch (PDOException $exception) {
            fail('Username atau email sudah digunakan', 409);
        }

        $id = (int) db()->lastInsertId();

        logAction($user['id'], "Tambah akun {$payload['username']} sebagai {$role}");

        success(['message' => 'Akun berhasil dibuat', 'data' => ['id' => $id]]);
    }

    if (in_array($method, ['PUT', 'PATCH'], true)) {
        if (!isHead($user)) {
            fail('Hanya kepala perpustakaan yang dapat memperbarui user', 403);
        }

        $id = intParam('id');
        if (!$id) {
            fail('ID user wajib diisi', 422);
        }

        $payload = jsonBody();
        $allowed = array_filter(only($payload, ['username', 'nama_lengkap', 'email', 'no_telp', 'alamat', 'role']), static fn($value) => $value !== null && $value !== '');

        if (isset($payload['password']) && $payload['password'] !== '') {
            $allowed['password'] = password_hash($payload['password'], PASSWORD_BCRYPT);
        }

        if (empty($allowed)) {
            fail('Tidak ada perubahan', 422);
        }

        $columns = [];
        $params = [':id' => $id];
        foreach ($allowed as $field => $value) {
            $columns[] = sprintf('%s = :%s', $field, $field);
            $params[':' . $field] = $value;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $columns) . ' WHERE id = :id';
        $stmt = db()->prepare($sql);

        try {
            $stmt->execute($params);
        } catch (PDOException $exception) {
            fail('Username atau email sudah digunakan', 409);
        }

        logAction($user['id'], "Perbarui akun #{$id}");

        success(['message' => 'User diperbarui']);
    }

    if ($method === 'DELETE') {
        if (!isHead($user)) {
            fail('Hanya kepala perpustakaan yang dapat menghapus user', 403);
        }

        $id = intParam('id');
        if (!$id) {
            fail('ID user wajib diisi', 422);
        }

        $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        logAction($user['id'], "Hapus akun #{$id}");

        success(['message' => 'User dihapus']);
    }

    fail('Metode tidak didukung', 405);
}

function handleLoans(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user = requireAuth();
    $pdo = db();

    if (!isStaff($user)) {
        fail('Modul peminjaman hanya untuk kepala perpustakaan / staff', 403);
    }

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT p.id, p.user_id, p.buku_id, p.tanggal_pinjam, p.tanggal_kembali, p.status,
                                    u.nama_lengkap AS peminjam, b.judul AS buku
                             FROM peminjaman p
                             JOIN users u ON u.id = p.user_id
                             JOIN buku b ON b.id = p.buku_id
                             ORDER BY p.created_at DESC
                             LIMIT 120');
        success(['data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $payload = jsonBody();

        ensure($payload, [
            'user_id' => 'Anggota wajib dipilih',
            'buku_id' => 'Buku wajib dipilih',
            'due_date' => 'Tanggal jatuh tempo wajib diisi',
        ]);

        try {
            $pdo->beginTransaction();

            $bookStmt = $pdo->prepare('SELECT id, jumlah_stok FROM buku WHERE id = :id FOR UPDATE');
            $bookStmt->execute([':id' => (int) $payload['buku_id']]);
            $book = $bookStmt->fetch();

            if (!$book) {
                $pdo->rollBack();
                fail('Buku tidak ditemukan', 404);
            }

            if ((int) $book['jumlah_stok'] < 1) {
                $pdo->rollBack();
                fail('Stok buku habis', 422);
            }

            $insert = $pdo->prepare('INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, tanggal_kembali, status)
                                     VALUES (:user_id, :buku_id, :tanggal_pinjam, :tanggal_kembali, :status)');
            $insert->execute([
                ':user_id' => (int) $payload['user_id'],
                ':buku_id' => (int) $payload['buku_id'],
                ':tanggal_pinjam' => $payload['tanggal_pinjam'] ?? date('Y-m-d'),
                ':tanggal_kembali' => $payload['due_date'],
                ':status' => 'dipinjam',
            ]);

            $loanId = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE buku SET jumlah_stok = jumlah_stok - 1 WHERE id = :id')
                ->execute([':id' => (int) $payload['buku_id']]);

            $pdo->commit();

            logAction($user['id'], "Catat peminjaman #{$loanId}");

            success(['message' => 'Peminjaman dicatat', 'data' => ['id' => $loanId]]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    if ($method === 'PATCH') {
        $id = intParam('id');
        if (!$id) {
            fail('ID peminjaman wajib diisi', 422);
        }

        $payload = jsonBody();
        $action = $payload['action'] ?? '';

        if ($action === 'return') {
            try {
                $pdo->beginTransaction();

                $loanStmt = $pdo->prepare('SELECT id, buku_id, status FROM peminjaman WHERE id = :id FOR UPDATE');
                $loanStmt->execute([':id' => $id]);
                $loan = $loanStmt->fetch();

                if (!$loan) {
                    $pdo->rollBack();
                    fail('Peminjaman tidak ditemukan', 404);
                }

                if ($loan['status'] === 'dikembalikan') {
                    $pdo->rollBack();
                    fail('Buku sudah dikembalikan', 409);
                }

                $pdo->prepare("UPDATE peminjaman SET status = 'dikembalikan' WHERE id = :id")
                    ->execute([':id' => $id]);

                $pdo->prepare('UPDATE buku SET jumlah_stok = jumlah_stok + 1 WHERE id = :id')
                    ->execute([':id' => (int) $loan['buku_id']]);

                $pdo->commit();

                logAction($user['id'], "Tandai peminjaman #{$id} dikembalikan");

                success(['message' => 'Peminjaman ditandai dikembalikan']);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }
        }

        if ($action === 'extend') {
            ensure($payload, [
                'due_date' => 'Tanggal jatuh tempo baru wajib diisi',
            ]);

            $stmt = $pdo->prepare('UPDATE peminjaman SET tanggal_kembali = :tanggal_kembali WHERE id = :id');
            $stmt->execute([
                ':tanggal_kembali' => $payload['due_date'],
                ':id' => $id,
            ]);

            logAction($user['id'], "Perpanjang peminjaman #{$id} sampai {$payload['due_date']}");

            success(['message' => 'Jatuh tempo diperpanjang']);
        }

        fail('Aksi tidak dikenali', 422);
    }

    if ($method === 'DELETE') {
        $id = intParam('id');
        if (!$id) {
            fail('ID peminjaman wajib diisi', 422);
        }

        try {
            $pdo->beginTransaction();

            $loanStmt = $pdo->prepare('SELECT id, buku_id, status FROM peminjaman WHERE id = :id FOR UPDATE');
            $loanStmt->execute([':id' => $id]);
            $loan = $loanStmt->fetch();

            if (!$loan) {
                $pdo->rollBack();
                fail('Peminjaman tidak ditemukan', 404);
            }

            if ($loan['status'] === 'dipinjam') {
                $pdo->prepare('UPDATE buku SET jumlah_stok = jumlah_stok + 1 WHERE id = :id')
                    ->execute([':id' => (int) $loan['buku_id']]);
            }

            $pdo->prepare('DELETE FROM peminjaman WHERE id = :id')->execute([':id' => $id]);

            $pdo->commit();

            logAction($user['id'], "Hapus peminjaman #{$id}");

            success(['message' => 'Peminjaman dihapus']);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    fail('Metode tidak didukung', 405);
}

function handleDashboard(): void
{
    $user = requireAuth();

    if (!isStaff($user)) {
        fail('Dashboard hanya untuk kepala perpustakaan / staff', 403);
    }

    $pdo = db();

    $totals = [
        'books' => (int) $pdo->query('SELECT COUNT(*) FROM buku')->fetchColumn(),
        'available_books' => (int) $pdo->query('SELECT COALESCE(SUM(jumlah_stok), 0) FROM buku')->fetchColumn(),
        'members' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = '" . ROLE_MEMBER . "'")->fetchColumn(),
        'active_loans' => (int) $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn(),
        'overdue_loans' => (int) $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali < CURRENT_DATE")->fetchColumn(),
    ];

    $recent = $pdo->query('SELECT p.id, b.judul, u.nama_lengkap AS peminjam, p.status, p.tanggal_kembali
                           FROM peminjaman p
                           JOIN buku b ON b.id = p.buku_id
                           JOIN users u ON u.id = p.user_id
                           ORDER BY p.created_at DESC
                           LIMIT 6')->fetchAll();

    $overdue = $pdo->query("SELECT p.id, b.judul, u.nama_lengkap AS peminjam, p.tanggal_kembali
                             FROM peminjaman p
                             JOIN buku b ON b.id = p.buku_id
                             JOIN users u ON u.id = p.user_id
                             WHERE p.status = 'dipinjam' AND p.tanggal_kembali < CURRENT_DATE
                             ORDER BY p.tanggal_kembali ASC
                             LIMIT 6")->fetchAll();

    success(['data' => [
        'totals' => $totals,
        'recent' => $recent,
        'overdue' => $overdue,
    ]]);
}

function handleLogs(): void
{
    $user = requireAuth();

    if (!isStaff($user)) {
        fail('Log aktivitas khusus untuk kepala perpustakaan / staff', 403);
    }

    $stmt = db()->query('SELECT l.id, l.aktivitas, l.tanggal AS created_at, u.nama_lengkap AS actor
                         FROM log_aktivitas l
                         LEFT JOIN users u ON u.id = l.user_id
                         ORDER BY l.tanggal DESC
                         LIMIT 60');

    success(['data' => $stmt->fetchAll()]);
}

/* --- Utilities ----------------------------------------------------------- */

function isStaff(array $user): bool
{
    return in_array($user['role'], [ROLE_HEAD, ROLE_STAFF], true);
}

function isHead(array $user): bool
{
    return $user['role'] === ROLE_HEAD;
}

function sanitizeYear($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $year = preg_replace('/\D/', '', (string) $value);

    if (strlen($year) !== 4) {
        return null;
    }

    return $year;
}
