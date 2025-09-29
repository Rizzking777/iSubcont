<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('log-activity'); // cek apakah sudah login dan punya akses ke menu ini

// Ambil jumlah user aktif hari ini
$active_today_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT id_user) AS active_users
    FROM tlog_login
    WHERE DATE(login_time) = CURDATE()
");
$active_today = mysqli_fetch_assoc($active_today_query)['active_users'];

// Ambil statistik login user (total login & last login)
$stats_query = mysqli_query($conn, "
    SELECT u.id_user, u.username, u.nik_user, 
           COUNT(l.id_log) AS total_login,
           MAX(l.login_time) AS last_login
    FROM tbl_user u
    LEFT JOIN tlog_login l ON u.id_user = l.id_user
    GROUP BY u.id_user, u.username, u.nik_user
    ORDER BY total_login DESC
");

$stats = get_login_log_stats($conn);

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

  /* Card biar lebih compact */
  .card-dashboard {
    border-radius: 15px;
    transition: all 0.3s ease;
    padding: 15px 10px;
  }

  .card-dashboard:hover {
    transform: translateY(-5px) scale(1.03);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
  }

  /* Icon animasi */
  .card-dashboard i {
    display: inline-block;
    transition: transform 0.3s ease, color 0.3s ease;
  }

  .card-dashboard:hover i {
    transform: rotate(10deg) scale(1.2);
    color: #0d6efd;
  }

  /* Judul kecil */
  .card-dashboard h6 {
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
  }

  /* Angka utama */
  .card-dashboard h3,
  .card-dashboard h5 {
    margin: 0;
    font-weight: 700;
  }

  /* Text kecil */
  .card-dashboard span {
    font-size: 0.8rem;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - User Activity</title>
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
  $page = 'log-activity';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Logging Activity User
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 15px;">

              <div class="row mb-3 g-3">
                <!-- Total Logins -->
                <div class="col-md-3 col-sm-6">
                  <div class="card text-center shadow-sm border-0 h-100 card-dashboard">
                    <div class="card-body">
                      <i class="bi bi-box-arrow-in-right text-primary fs-2 mb-2"></i>
                      <h6 class="text-muted">Total Logins</h6>
                      <h3 class="fw-bold text-primary"><?= $stats['total_logins']; ?></h3>
                    </div>
                  </div>
                </div>

                <!-- Unique Users -->
                <div class="col-md-3 col-sm-6">
                  <div class="card text-center shadow-sm border-0 h-100 card-dashboard">
                    <div class="card-body">
                      <i class="bi bi-people text-success fs-2 mb-2"></i>
                      <h6 class="text-muted">Unique Users</h6>
                      <h3 class="fw-bold text-success"><?= $stats['unique_users']; ?></h3>
                    </div>
                  </div>
                </div>

                <!-- Peak Login Time -->
                <div class="col-md-3 col-sm-6">
                  <div class="card text-center shadow-sm border-0 h-100 card-dashboard">
                    <div class="card-body">
                      <i class="bi bi-clock-history text-warning fs-2 mb-2"></i>
                      <h6 class="text-muted">Peak Login Time</h6>
                      <h5 class="fw-bold"><?= $stats['peak_login']['hour']; ?></h5>
                      <span class="text-muted"><?= $stats['peak_login']['jumlah']; ?> logins</span>
                    </div>
                  </div>
                </div>

                <!-- Latest Login -->
                <div class="col-md-3 col-sm-6">
                  <div class="card text-center shadow-sm border-0 h-100 card-dashboard">
                    <div class="card-body">
                      <i class="bi bi-person-check text-info fs-2 mb-2"></i>
                      <h6 class="text-muted">Latest Login</h6>
                      <h5 class="fw-bold"><?= $stats['latest']['username']; ?></h5>
                      <span class="text-muted"><?= $stats['latest']['time_ago']; ?></span>
                    </div>
                  </div>
                </div>
              </div>


              <!-- Tabel Statistik Login -->
              <div class="card">
                <div class="card-header text-black" style="background-color: #f0e6d2;">
                  Statistik Login User
                </div>
                <div class="card-body" style="margin-top: 15px;">

                  
                    <div class="table-responsive" id="userTable">
                      <table id="example1" class="table table-bordered table-striped text-center align-middle">
                        <thead class="table-light">
                          <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">Username</th>
                            <th class="text-center">NIK</th>
                            <th class="text-center">Total Login</th>
                            <th class="text-center">Last Login</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php $i = 1; ?>
                          <?php while ($row = mysqli_fetch_assoc($stats_query)): ?>
                            <tr>
                              <td><?= $i++; ?></td>
                              <td><?= htmlspecialchars($row['username']); ?></td>
                              <td><?= htmlspecialchars($row['nik_user']); ?></td>
                              <td><span class="badge bg-info"><?= $row['total_login']; ?></span></td>
                              <td><?= $row['last_login'] ? $row['last_login'] : '<span class="text-muted">Belum pernah login</span>'; ?></td>
                            </tr>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div>
                </div>
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