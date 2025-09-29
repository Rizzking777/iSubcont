<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('log-upload'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

$logsUpload = null;

if (isset($_POST['filter'])) {
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal_awal']);

    if (!empty($tanggal)) {
        // Filter hanya tanggal tertentu
        $sql = "SELECT * FROM `tlog_upload_master`
                WHERE DATE(created_at) = '$tanggal'
                ORDER BY created_at DESC;";
    } else {
        // Kalau tanggal kosong, bisa kasih hasil kosong atau fallback semua
        $sql = "SELECT * FROM tlog_user WHERE 1=0"; // tidak tampilkan apa2
    }

    $logsUpload = mysqli_query($conn, $sql);
}

?>

<style>
    .toast-progress {
        height: 4px;
        width: 100%;
        overflow: hidden;
        border-radius: 0 0 0.375rem 0.375rem;
    }

    .toast-progress-bar {
        height: 100%;
        animation: progressBar 3s linear forwards;
    }

    @keyframes progressBar {
        0% {
            width: 100%;
        }

        100% {
            width: 0%;
        }
    }

    .hover-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .icon-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        border-radius: 50%;
    }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>iSubcont - Logging Upload Data</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <!-- Favicons -->
    <link href="../assets/img/Logo-Stg.png" rel="icon">
    <link href="../assets/img/Logo-Stg.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">

    <!-- Template Main CSS File -->
    <link href="../assets/css/style.css" rel="stylesheet">

    <!-- Tambahkan pustaka Select2 di bagian <head> -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Pastikan jQuery ada -->

    <!-- Select2 CSS & JS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Datatables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">

</head>

<body>

    <!-- Header -->
    <?php
    $page = 'log-upload';
    include_once __DIR__ . '/../includes/header.php';
    ?>
    <!-- End Header -->

    <main id="main" class="main">

        <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
                Logging Upload Data Master
            </h1>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">

                        <div class="card-body" style="margin-top: 15px;">

                            <!-- Filter Form -->
                            <form method="post" class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label for="tanggal_awal" class="form-label">Tanggal</label>
                                    <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control" required
                                        value="<?= $_POST['tanggal_awal'] ?? '' ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end gap-2">
                                    <button type="submit" name="filter" class="btn btn-success w-auto">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                    <a href="<?= basename($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary w-auto">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </a>
                                </div>
                            </form>

                            <!-- Kondisi tampil tabel -->
                            <?php if (isset($_POST['filter'])): ?>
                                <div class="table-responsive" id="logUploadSection">
                                    <table id="example1" class="table table-bordered table-striped text-center align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width: 5%">#</th>
                                                <th class="text-center" style="width: 15%">Tanggal</th>
                                                <th class="text-center" style="width: 10%">User</th>
                                                <th class="text-center" style="width: 20%">File</th>
                                                <th class="text-center" style="width: 7%">Total</th>
                                                <th class="text-center" style="width: 7%">Success</th>
                                                <th class="text-center" style="width: 7%">Failed</th>
                                                <th class="text-center" style="width: 7%">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            while ($row = $logsUpload->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td><?= date('d M Y H:i:s', strtotime($row['created_at'])); ?></td>
                                                    <td><?= htmlspecialchars($row['username']); ?></td>
                                                    <td><?= htmlspecialchars($row['file_name']); ?></td>
                                                    <td><?= $row['total_rows']; ?></td>
                                                    <td><?= $row['success_rows']; ?></td>
                                                    <td><?= $row['failed_rows']; ?></td>
                                                    <td>
                                                        <?php if ($row['status'] === 'success'): ?>
                                                            <span class="badge bg-success">Success</span>
                                                        <?php elseif ($row['status'] === 'partial'): ?>
                                                            <span class="badge bg-warning text-dark">Partial</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Failed</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-light">
                                    <i class="bi bi-info-circle"></i> Silakan pilih tanggal lalu klik <b>Search</b> untuk menampilkan log upload.
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </section>


    </main><!-- End #main -->

    <!-- ======= Footer ======= -->
    <?php include_once __DIR__ . '/../includes/footer.php' ?>
    <!-- End Footer -->

    <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Vendor JS Files -->
    <script src="../assets/vendor/apexcharts/apexcharts.min.js"></script>
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/chart.js/chart.umd.js"></script>
    <script src="../assets/vendor/echarts/echarts.min.js"></script>
    <script src="../assets/vendor/quill/quill.min.js"></script>
    <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="../assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="../assets/vendor/php-email-form/validate.js"></script>

    <!-- Template Main JS File -->
    <script src="../assets/js/main.js"></script>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- DataTables core -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <!-- Responsive extension -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Buttons extension -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <script>
        $(function() {
            $("#example1").DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "buttons": [""]
            }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
            $('#example2').DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": false,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
            });
        });
    </script>

    <?php include_once __DIR__ . '/../includes/notification.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastEl = document.getElementById('liveToast');
            if (toastEl) {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 3000
                });
                toast.show();
            }
        });
    </script>

</body>

</html>