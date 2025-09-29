<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('archive_komponen'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

// Ambil data komponen proses
$sql = "
    SELECT 
    k_out.id_komponen AS id_output,
    MAX(k.model) AS model,
    MAX(k.style) AS style,
    GROUP_CONCAT(DISTINCT k_in.nama_komponen ORDER BY k_in.nama_komponen SEPARATOR ', ') AS input_komponen,
    k_out.nama_komponen AS output_komponen
FROM tbl_komponen_proses p
JOIN tbl_komponen k_in ON p.id_input = k_in.id_komponen
JOIN tbl_komponen k_out ON p.id_output = k_out.id_komponen
JOIN tbl_komponen k ON k.id_komponen = p.id_output
WHERE k_in.is_deleted = 1 
  AND k_out.is_deleted = 1
GROUP BY k_out.id_komponen
ORDER BY model, style;
";

$result = $conn->query($sql);

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
</style>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>iSubcont - Archive Komponen</title>
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
    $page = 'archive_komponen';
    include_once __DIR__ . '/../includes/header.php';
    ?>
    <!-- End Header -->

    <main id="main" class="main">

        <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
                Archive Komponen
            </h1>
        </div>

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">

                        <div class="card-body" style="margin-top: 10px;">

                            <!-- Table with stripped rows -->
                            <div class="table-responsive" id="userTable">
                                <table id="example1" class="table table-bordered table-striped text-center align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th class="text-center">Model</th>
                                            <th class="text-center">Style</th>
                                            <th class="text-center">Komponen Input</th>
                                            <th class="text-center">Komponen Output</th>
                                            <th class="text-center">Options</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">

                                        <?php $i = 1; ?>
                                        <?php foreach ($result as $row) : ?>
                                            <tr>
                                                <td><?= $i ?></td>
                                                <td><?= htmlspecialchars($row['model']) ?></td>
                                                <td><?= htmlspecialchars($row['style']) ?></td>
                                                <td><?= htmlspecialchars($row['input_komponen']) ?></td>
                                                <td><?= htmlspecialchars($row['output_komponen']) ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-primary" type="button" id="dropdownMenu<?= $row['id_output']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-three-dots"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <form action="./../config/function.php" method="post">
                                                                    <input type="hidden" name="id_output" value="<?= htmlspecialchars($row['id_output']); ?>">
                                                                    <button type="submit" name="restore-komponen" class="dropdown-item text-info" onclick="return confirm('Yakin ingin restore data komponen ini?');">
                                                                        <i class="bi bi-arrow-counterclockwise me-2"></i> Restore
                                                                    </button>
                                                                    <button type="submit" name="delete-komponen" class="dropdown-item text-danger" onclick="return confirm('Yakin ingin hapus secara permanent data komponen ini?');">
                                                                        <i class="bi bi-trash me-2"></i> Delete
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $i++; ?>
                                        <?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                            <!-- End Table with stripped rows -->
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