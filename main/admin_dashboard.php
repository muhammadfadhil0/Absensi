<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get attendance statistics
$today = date('Y-m-d');
$query_stats = "SELECT 
    COUNT(*) as total_attendance,
    SUM(CASE WHEN status = 'tepat waktu' THEN 1 ELSE 0 END) as on_time,
    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as late
    FROM datang 
    WHERE DATE(tanggal) = ?";

$stmt = $conn->prepare($query_stats);
$stmt->bind_param("s", $today);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent attendance records
$query_recent = "SELECT d.*, u.namaLengkap 
    FROM datang d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.tanggal DESC, d.waktu_absen DESC 
    LIMIT 10";

$recent_records = $conn->query($query_recent);

// Get all users for schedule management
$query_users = "SELECT * FROM users WHERE id != 1 ORDER BY namaLengkap ASC";
$users = $conn->query($query_users);

// Get permission requests
$query_permissions = "SELECT i.*, u.namaLengkap 
    FROM ijin i 
    JOIN users u ON i.user_id = u.id 
    ORDER BY i.tanggal_mulai DESC";
$permissions = $conn->query($query_permissions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SMAGA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: rgb(238, 236, 226);
            font-family: 'Merriweather', serif;
        }
        .dashboard-container {
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .btn-primary {
            background-color: #DA7756;
            border: none;
        }
        .btn-primary:hover {
            background-color: #A95342;
        }
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #DA7756;
        }
        .nav-pills .nav-link {
            color: #333;
        }
        .nav-pills .nav-link.active {
            background-color: #DA7756;
        }
        .modal-header {
            background-color: #DA7756;
            color: white;
        }
        .time-input {
            width: 100px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand" href="logout.php">
                <img src="assets/smagaedu.png" alt="SMAGA Logo" width="40">
                Admin Dashboard
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_action.php?action=export_today">
                            <i class="bi bi-download"></i> Export Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_action.php?action=logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <!-- Navigation Pills -->
        <div class="d-flex justify-content-center">
            <ul class="nav nav-pills mb-4" id="adminTabs" role="tablist">
            <li class="nav-item mx-1" role="presentation">
                <button class="nav-link active px-3" data-bs-toggle="pill" data-bs-target="#overview">
                <i class="bi bi-grid"></i><p style="margin: 0; padding: 0; font-size: 10px;">Overview</p>
                </button>
            </li>
            <li class="nav-item mx-1" role="presentation">
                <button class="nav-link px-3" data-bs-toggle="pill" data-bs-target="#attendance">
                <i class="bi bi-calendar-check"></i><p style="margin: 0; padding: 0; font-size: 10px;">Riwayat</p>
                </button>
            </li>
            <li class="nav-item mx-1" role="presentation">
                <button class="nav-link px-3" data-bs-toggle="pill" data-bs-target="#schedules">
                <i class="bi bi-clock"></i><p style="margin: 0; padding: 0; font-size: 10px;">Jadwal</p>
                </button>
            </li>
            <li class="nav-item mx-1" role="presentation">
                <button class="nav-link px-3" data-bs-toggle="pill" data-bs-target="#permissions">
                <i class="bi bi-file-text"></i><p style="margin: 0; padding: 0; font-size: 10px;">Perizinan</p>
                </button>
            </li>
            </ul>
        </div>
        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview">
                <div class="row mb-4 g-4">
                    <div class="col-md-4">
                        <div class="stat-card h-100 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted mb-0">Total Kehadiran</h6>
                                <i class="bi bi-people-fill text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                            <h3 class="stat-number mb-0"><?php echo $stats['total_attendance']; ?></h3>
                            <small class="text-muted">Hari ini</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card h-100 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted mb-0">Tepat Waktu</h6>
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 1.5rem;"></i>
                            </div>
                            <h3 class="stat-number mb-0"><?php echo $stats['on_time']; ?></h3>
                            <small class="text-success"><?php echo $stats['total_attendance'] ? round(($stats['on_time']/$stats['total_attendance'])*100) : 0; ?>% dari total</small>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="stat-card h-100 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="text-muted mb-0">Terlambat</h6>
                                <i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 1.5rem;"></i>
                            </div>
                            <h3 class="stat-number mb-0"><?php echo $stats['late']; ?></h3>
                            <small class="text-danger"><?php echo $stats['total_attendance'] ? round(($stats['late']/$stats['total_attendance'])*100) : 0; ?>% dari total</small>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Kehadiran Terkini</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                        <th>Metode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($record = $recent_records->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['namaLengkap']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($record['tanggal'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($record['waktu_absen'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $record['status'] === 'tepat waktu' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['metode_absen']); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance History Tab -->
            <div class="tab-pane fade" id="attendance">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Riwayat Absensi</h5>
                        <div class="mb-4">
                            <div class="row g-2">
                                <div class="col-12 col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="searchName" placeholder="Cari nama guru...">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <p class="p-0 m-0 mt-2">Dari</p>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" class="form-control" id="startDate" placeholder="Dari">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <p class="p-0 m-0 mt-2">Sampai</p>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" class="form-control" id="endDate" placeholder="Sampai">
                                    </div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="d-grid gap-2 d-md-flex mt-2 justify-content-md-end">
                                        <button class="btn btn-primary w-100" id="filterAttendance">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                        <button class="btn btn-outline-secondary w-100" id="resetFilter">
                                            <i class="bi bi-x-circle"></i> Hapus Filter
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populated via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedules Tab -->
            <div class="tab-pane fade" id="schedules">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Jadwal Guru</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Senin</th>
                                        <th>Selasa</th>
                                        <th>Rabu</th>
                                        <th>Kamis</th>
                                        <th>Jumat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['namaLengkap']); ?></td>
                                        <td><?php echo $user['senin_datang'] ? date('H:i', strtotime($user['senin_datang'])) : '-'; ?></td>
                                        <td><?php echo $user['selasa_datang'] ? date('H:i', strtotime($user['selasa_datang'])) : '-'; ?></td>
                                        <td><?php echo $user['rabu_datang'] ? date('H:i', strtotime($user['rabu_datang'])) : '-'; ?></td>
                                        <td><?php echo $user['kamis_datang'] ? date('H:i', strtotime($user['kamis_datang'])) : '-'; ?></td>
                                        <td><?php echo $user['jumat_datang'] ? date('H:i', strtotime($user['jumat_datang'])) : '-'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editSchedule(<?php echo $user['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions Tab -->
            <div class="tab-pane fade" id="permissions">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Perizinan</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Jenis</th>
                                        <th>Mulai</th>
                                        <th>Selesai</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($permission = $permissions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($permission['namaLengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($permission['perizinan']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($permission['tanggal_mulai'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($permission['tanggal_selesai'])); ?></td>
                                        <td><?php echo htmlspecialchars($permission['keterangan']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="deletePermission(<?php echo $permission['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm">
                        <input type="hidden" id="userId" name="user_id">
                        <div class="mb-3">
                            <label class="form-label">Senin</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control time-input" name="senin_datang">
                                <span class="align-self-center">sampai</span>
                                <input type="time" class="form-control time-input" name="senin_pulang">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Selasa</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control time-input" name="selasa_datang">
                                <span class="align-self-center">sampai</span>
                                <input type="time" class="form-control time-input" name="selasa_pulang">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rabu</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control time-input" name="rabu_datang">
                                <span class="align-self-center">sampai</span>
                                <input type="time" class="form-control time-input" name="rabu_pulang">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kamis</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control time-input" name="kamis_datang">
                                <span class="align-self-center">sampai</span>
                                <input type="time" class="form-control time-input" name="kamis_pulang">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jumat</label>
                            <div class="d-flex gap-2">
                                <input type="time" class="form-control time-input" name="jumat_datang">
                                <span class="align-self-center">sampai</span>
                                <input type="time" class="form-control time-input" name="jumat_pulang">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="saveSchedule">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="attendanceForm">
                        <input type="hidden" id="attendanceId" name="id">
                        <div class="mb-3">
                            <label class="form-label">Waktu</label>
                            <input type="time" class="form-control" name="waktu_absen" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" required>
                                <option value="tepat waktu">Tepat Waktu</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="pulang">Pulang</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="saveAttendance">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Functions for attendance management
        function loadAttendance(startDate = '', endDate = '', searchName = '') {
            fetch(`admin_action.php?action=get_attendance&start=${startDate}&end=${endDate}&search=${encodeURIComponent(searchName)}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#attendanceTable tbody');
                    tbody.innerHTML = '';
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada data</td></tr>';
                        return;
                    }
                    data.forEach(record => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${record.namaLengkap}</td>
                                <td>${record.tanggal}</td>
                                <td>${record.waktu_absen}</td>
                                <td><span class="badge ${record.status === 'tepat waktu' ? 'bg-success' : 'bg-danger'}">${record.status}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editAttendance(${record.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteAttendance(${record.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function editAttendance(id) {
            fetch(`admin_action.php?action=get_attendance_detail&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('attendanceId').value = data.id;
                    document.querySelector('input[name="waktu_absen"]').value = data.waktu_absen;
                    document.querySelector('select[name="status"]').value = data.status;
                    new bootstrap.Modal(document.getElementById('editAttendanceModal')).show();
                });
        }

        function deleteAttendance(id) {
            if (confirm('Yakin ingin menghapus data absensi ini?')) {
                fetch(`admin_action.php?action=delete_attendance&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadAttendance();
                        }
                    });
            }
        }

        // Functions for schedule management
        function editSchedule(userId) {
            fetch(`admin_action.php?action=get_schedule&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('userId').value = data.id;
                    document.querySelector('input[name="senin_datang"]').value = data.senin_datang;
                    document.querySelector('input[name="senin_pulang"]').value = data.senin_pulang;
                    // Set other days similarly
                    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
                });
        }

        function deletePermission(id) {
            if (confirm('Yakin ingin menghapus data perizinan ini?')) {
                fetch(`admin_action.php?action=delete_permission&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            loadAttendance();

            document.getElementById('filterAttendance').addEventListener('click', function() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                loadAttendance(startDate, endDate);
            });

            document.getElementById('resetFilter').addEventListener('click', function() {
                document.getElementById('startDate').value = '';
                document.getElementById('endDate').value = '';
                document.getElementById('searchName').value = '';
                loadAttendance('', '', '');
            });

            document.getElementById('searchName').addEventListener('input', function() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                const searchName = this.value;
                loadAttendance(startDate, endDate, searchName);
            });


            document.getElementById('saveAttendance').addEventListener('click', function() {
                const formData = new FormData(document.getElementById('attendanceForm'));
                fetch('admin_action.php?action=update_attendance', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
                        loadAttendance();
                    }
                });
            });

            document.getElementById('saveSchedule').addEventListener('click', function() {
                const formData = new FormData(document.getElementById('scheduleForm'));
                fetch('admin_action.php?action=update_schedule', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                        location.reload();
                    }
                });
            });
        });
    </script>
</body>
</html>