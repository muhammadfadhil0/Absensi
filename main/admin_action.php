<?php
session_start();
require_once 'koneksi.php';

// Set error handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_GET['action']) && $_GET['action'] === 'login') {
        // Handle login POST request
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin_dashboard.php');
            exit();
        } else {
            header('Location: admin_login.php?error=1');
            exit();
        }
    }
    header('Location: admin_login.php');
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'logout':
            session_destroy();
            header('Location: admin_login.php');
            exit();

            case 'get_attendance':
                header('Content-Type: application/json');
                $startDate = !empty($_GET['start']) ? $_GET['start'] : date('Y-m-d');
                $endDate = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-d');
                $search = $_GET['search'] ?? '';
                
                // Log for debugging
                error_log("Get Attendance - Start: $startDate, End: $endDate, Search: $search");
            
                $query = "SELECT d.*, u.namaLengkap 
                        FROM datang d 
                        JOIN users u ON d.user_id = u.id 
                        WHERE (? = '' OR DATE(d.tanggal) >= ?) 
                        AND (? = '' OR DATE(d.tanggal) <= ?)
                        AND (? = '' OR u.namaLengkap LIKE CONCAT('%', ?, '%'))
                        ORDER BY d.tanggal DESC, d.waktu_absen DESC";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssss", $startDate, $startDate, $endDate, $endDate, $search, $search);
                
                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }
                
                $result = $stmt->get_result();
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $row['tanggal'] = date('d/m/Y', strtotime($row['tanggal']));
                    $row['waktu_absen'] = date('H:i', strtotime($row['waktu_absen']));
                    $data[] = $row;
                }
                
                echo json_encode($data);
                break;


                case 'get_attendance_detail':
                    header('Content-Type: application/json');
                    $id = $_GET['id'] ?? 0;
                    
                    $query = "SELECT * FROM datang WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        $row['waktu_absen'] = date('H:i', strtotime($row['waktu_absen']));
                        echo json_encode($row);
                    } else {
                        throw new Exception("Record not found");
                    }
                    break;


        case 'delete_attendance':
            header('Content-Type: application/json');
            $id = $_GET['id'] ?? 0;
            
            $query = "DELETE FROM datang WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to delete attendance record");
            }
            break;

        case 'update_attendance':
            header('Content-Type: application/json');
            $id = $_POST['id'] ?? 0;
            $waktu = $_POST['waktu_absen'] ?? '';
            $status = $_POST['status'] ?? '';
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            
            $query = "UPDATE datang SET waktu_absen = ?, status = ?, tanggal = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $waktu, $status, $tanggal, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to update attendance record");
            }
            break;

        case 'update_schedule':
            header('Content-Type: application/json');
            $userId = $_POST['user_id'] ?? 0;
            $days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];
            $updates = [];
            $params = [];
            $types = '';

            foreach ($days as $day) {
                if (isset($_POST[$day . '_datang']) && isset($_POST[$day . '_pulang'])) {
                    $datang = $_POST[$day . '_datang'] ? $_POST[$day . '_datang'] : null;
                    $pulang = $_POST[$day . '_pulang'] ? $_POST[$day . '_pulang'] : null;
                    
                    $updates[] = "$day" . "_datang = ?, $day" . "_pulang = ?";
                    $params[] = $datang;
                    $params[] = $pulang;
                    $types .= "ss";
                }
            }

            if (!empty($updates)) {
                $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $params[] = $userId;
                $types .= "i";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception("Failed to update schedule");
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No data to update']);
            }
            break;

        case 'delete_permission':
            header('Content-Type: application/json');
            $id = $_GET['id'] ?? 0;
            
            $query = "DELETE FROM ijin WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to delete permission record");
            }
            break;

        case 'get_user_schedule':
            header('Content-Type: application/json');
            $userId = $_GET['user_id'] ?? 0;
            
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'data' => $row
                ]);
            } else {
                throw new Exception("User not found");
            }
            break;

        case 'export_attendance':
            $startDate = $_GET['start'] ?? date('Y-m-d');
            $endDate = $_GET['end'] ?? date('Y-m-d');
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=absensi_' . $startDate . '_to_' . $endDate . '.csv');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
            
            fputcsv($output, [
                'Nama',
                'Tanggal',
                'Waktu',
                'Status',
                'Metode Absen',
                'IP Address'
            ]);
            
            $query = "SELECT u.namaLengkap, d.tanggal, d.waktu_absen, d.status, 
                     d.metode_absen, d.ip_address
                     FROM datang d
                     JOIN users u ON d.user_id = u.id
                     WHERE d.tanggal BETWEEN ? AND ?
                     ORDER BY d.tanggal ASC, d.waktu_absen ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['namaLengkap'],
                    $row['tanggal'],
                    $row['waktu_absen'],
                    $row['status'],
                    $row['metode_absen'],
                    $row['ip_address']
                ]);
            }
            
            fclose($output);
            exit();

        default:
            header('Location: admin_dashboard.php');
            exit();
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>