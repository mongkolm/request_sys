<?php
// กำหนดชื่อหน้า
$page_title = "แดชบอร์ดผู้ดูแลระบบ";

// เชื่อมต่อกับฐานข้อมูล
require_once 'config/db_connect.php';

// ตรวจสอบว่ามีการล็อกอินและเป็นแอดมินหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// ดึงข้อมูลสถิติรายการแจ้งซ่อม
$query = "SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(*) as total_count
          FROM repair_requests";
$result = sqlsrv_query($conn, $query);
$request_stats = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

// ดึงข้อมูลสถิติตามหมวดหมู่
$query = "SELECT c.category_id, c.category_name, COUNT(r.request_id) as request_count
          FROM categories c
          LEFT JOIN repair_requests r ON c.category_id = r.category_id
          GROUP BY c.category_id, c.category_name
          ORDER BY request_count DESC";
$category_stats_result = sqlsrv_query($conn, $query);

// เก็บข้อมูลไว้ในอาร์เรย์เพื่อใช้ซ้ำได้
$category_stats = [];
if ($category_stats_result) {
    while ($row = sqlsrv_fetch_array($category_stats_result, SQLSRV_FETCH_ASSOC)) {
        $category_stats[] = $row;
    }
}

// ดึงรายการแจ้งซ่อมล่าสุด
$query = "SELECT TOP 10 r.*, c.category_name, u.fullname as requester_name
          FROM repair_requests r
          JOIN categories c ON r.category_id = c.category_id
          JOIN users u ON r.user_id = u.user_id
          ORDER BY r.created_at DESC";
$recent_requests_result = sqlsrv_query($conn, $query);

// เก็บข้อมูลไว้ในอาร์เรย์เพื่อใช้ซ้ำได้
$recent_requests = [];
if ($recent_requests_result) {
    while ($row = sqlsrv_fetch_array($recent_requests_result, SQLSRV_FETCH_ASSOC)) {
        $recent_requests[] = $row;
    }
}

// ดึงจำนวนผู้ใช้งาน
$query = "SELECT COUNT(*) as user_count FROM users WHERE role = 'user'";
$result = sqlsrv_query($conn, $query);
$user_count = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)['user_count'];

// ดึงจำนวนหมวดหมู่
$query = "SELECT COUNT(*) as category_count FROM categories";
$result = sqlsrv_query($conn, $query);
$category_count = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)['category_count'];

// แสดงหน้าเว็บ
include 'includes/header.php';
?>

<style>
    @media (max-width: 768px) {
        .card .d-flex.align-items-center {
            flex-direction: column;
            text-align: center;
        }

        .card .d-flex.align-items-center .flex-grow-1 {
            margin-left: 0 !important;
            margin-top: 12px;
        }

        .chart-container {
            min-height: 300px;
        }

        .card .badge {
            white-space: normal;
        }

        .table-responsive {
            overflow-x: auto;
        }
    }

    @media (max-width: 576px) {
        .chart-container {
            min-height: 260px;
        }
    }
</style>

<!-- หัวข้อหน้า -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="bx bx-tachometer me-2"></i>แดชบอร์ดผู้ดูแลระบบ
    </h1>
    <div>
        <a href="create_request.php" class="btn btn-outline-primary me-2">
            <i class="bx bx-plus-circle me-1"></i>แจ้งซ่อมใหม่
        </a>
        <a href="admin_settings.php" class="btn btn-primary">
            <i class="bx bx-cog me-1"></i>ตั้งค่า
        </a>
    </div>
</div>

<!-- ส่วนต้อนรับ -->
<div class="card border-0 shadow mb-4">
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-md-8">
                <div class="p-4 p-md-5">
                    <h1 class="text-primary fw-bold">ยินดีต้อนรับเข้าสู่ระบบแจ้งซ่อม</h1>
                    <p class="text-muted">ศูนย์เทคโนโลยีสารสนเทศ มูลนิธิโครงการหลวง</p>
                </div>
            </div>
            <div class="col-md-4 d-none d-md-block text-end">
                <i class="bx bx-wrench text-primary p-4" style="font-size: 8rem; opacity: 0.1;"></i>
            </div>
        </div>
    </div>
</div>

<!-- บัตรสรุปสถิติ -->
<div class="row mb-4">
    <!-- รายการทั้งหมด -->
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #EBF3FE;">
                            <i class="bx bx-package text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-primary"><?php echo $request_stats['total_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รายการทั้งหมด</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- เสร็จสิ้น -->
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E6F8F1;">
                            <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-success"><?php echo $request_stats['completed_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">เสร็จสิ้น</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- กำลังดำเนินการ -->
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #E7F4FF;">
                            <i class="bx bx-loader text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-info"><?php echo $request_stats['in_progress_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">กำลังดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- รอดำเนินการ -->
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="rounded p-3" style="background-color: #FFF6E9;">
                            <i class="bx bx-time text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h3 class="h5 mb-0 text-warning"><?php echo $request_stats['pending_count']; ?> รายการ</h3>
                        <p class="text-muted mb-0">รอดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- แผนภูมิ -->
<div class="row mb-4">
    <!-- แผนภูมิสถานะ -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card border-0 shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-pie-chart me-2"></i>การกระจายตามสถานะ
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:350px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- แผนภูมิหมวดหมู่ -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card border-0 shadow h-100">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">
                    <i class="bx bx-pie-chart me-2"></i>การกระจายตามหมวดหมู่
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:350px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- รายการแจ้งซ่อมล่าสุด -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-bold text-primary">
            <i class="bx bx-list-ul me-2"></i>รายการแจ้งซ่อมล่าสุด
        </h6>
        <a href="admin_requests.php" class="btn btn-sm btn-primary">
            ดูทั้งหมด <i class="bx bx-right-arrow-alt"></i>
        </a>
    </div>
    <div class="card-body">
        <?php if (count($recent_requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>หมายเลข</th>
                            <th>ผู้แจ้ง</th>
                            <th>เรื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>สถานะ</th>
                            <th>ความสำคัญ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
                                <td>#<?php echo $request['request_id']; ?></td>
                                <td><?php echo $request['requester_name']; ?></td>
                                <td><?php echo $request['title']; ?></td>
                                <td><?php echo $request['category_name']; ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => '<span class="badge bg-warning text-dark">รอดำเนินการ</span>',
                                        'in_progress' => '<span class="badge bg-info text-white">กำลังดำเนินการ</span>',
                                        'completed' => '<span class="badge bg-success">เสร็จสิ้น</span>',
                                        'rejected' => '<span class="badge bg-danger">ยกเลิก</span>'
                                    ];
                                    echo $status_badges[$request['status']];
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $priority_badges = [
                                        'low' => '<span class="badge bg-success">ต่ำ</span>',
                                        'medium' => '<span class="badge bg-warning text-dark">ปานกลาง</span>',
                                        'high' => '<span class="badge bg-danger">สูง</span>',
                                        'urgent' => '<span class="badge bg-danger"><i class="bx bx-error-circle me-1"></i>เร่งด่วน</span>'
                                    ];
                                    echo $priority_badges[$request['priority']];
                                    ?>
                                </td>
                                <td>
                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bx bx-show-alt"></i> ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bx bx-clipboard-x text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3">ไม่มีรายการแจ้งซ่อม</h5>
                <p class="text-muted">ยังไม่มีรายการแจ้งซ่อมในระบบ</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card {
    border-radius: 10px;
    overflow: hidden;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.05);
}

.table th {
    font-weight: 500;
}

/* Animation for cards */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}
</style>

<!-- สคริปต์สำหรับสร้างแผนภูมิ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // แผนภูมิวงกลมแสดงการกระจายตามสถานะ
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['รอดำเนินการ', 'กำลังดำเนินการ', 'เสร็จสิ้น', 'ยกเลิก'],
            datasets: [{
                data: [
                    <?php echo $request_stats['pending_count']; ?>,
                    <?php echo $request_stats['in_progress_count']; ?>,
                    <?php echo $request_stats['completed_count']; ?>,
                    <?php echo $request_stats['rejected_count']; ?>
                ],
                backgroundColor: [
                    '#FFC107',
                    '#0DCAF0',
                    '#20C997',
                    '#DC3545'
                ],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            family: 'Prompt'
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'สถานะรายการแจ้งซ่อม',
                    font: {
                        size: 16,
                        family: 'Prompt'
                    }
                }
            },
            cutout: '65%',
            animation: {
                animateScale: true
            }
        }
    });
    
    // แผนภูมิวงกลมแสดงการกระจายตามหมวดหมู่
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryData = [];
    const categoryLabels = [];
    const categoryColors = [
        '#4E73DF',
        '#1CC88A',
        '#36B9CC',
        '#F6C23E',
        '#E74A3B',
        '#858796'
    ];
    
    <?php 
    foreach ($category_stats as $category):
    ?>
        categoryLabels.push('<?php echo $category['category_name']; ?>');
        categoryData.push(<?php echo $category['request_count']; ?>);
    <?php endforeach; ?>
    
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: categoryColors,
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            family: 'Prompt'
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'หมวดหมู่รายการแจ้งซ่อม',
                    font: {
                        size: 16,
                        family: 'Prompt'
                    }
                }
            },
            cutout: '65%',
            animation: {
                animateScale: true
            }
        }
    });
});
</script>

<?php
// แสดงส่วน footer
include 'includes/footer.php';
?>