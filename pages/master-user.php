<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('master_user'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

$users = mysqli_query($conn, "SELECT u.* , r.role_name
FROM tbl_user u
JOIN roles r ON u.role_id = r.id
WHERE u.is_deleted = '0'
ORDER BY timestamp DESC;");

// ambil semua role
$sql_roles = "SELECT id, role_name FROM roles WHERE is_deleted = '0' ORDER BY role_name ASC";
$result_roles = $conn->query($sql_roles);

$roles = [];
if ($result_roles->num_rows > 0) {
  while ($row = $result_roles->fetch_assoc()) {
    $roles[] = $row;
  }
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
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Master User</title>
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
  $page = 'master_user';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Master Data User
      </h1>
    </div>

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">

          <!-- Header -->
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center" id="tambahUserModalLabel">
              <i class="bi bi-person-plus me-2"></i> Tambah Data User
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">
              <div class="row g-3">
                <input type="hidden" name="updated_by" id="updated_by" class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
                <div class="col-md-6">
                  <label for="username" class="form-label">Username</label>
                  <input type="text" name="username" id="username" class="form-control" placeholder="Input username" required>
                </div>
                <div class="col-md-6">
                  <label for="nik_user" class="form-label">NIK</label>
                  <input type="text" name="nik_user" id="nik_user" class="form-control" placeholder="Input NIK" required>
                </div>
                <div class="col-md-6">
                  <label for="role_id" class="form-label">Role</label>
                  <select name="role_id" id="role_id" class="form-select" required>
                    <option value="">Pilih role</option>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" name="password" id="password" class="form-control" placeholder="Input password" required>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="submit-user">
                <i class="bi bi-check-circle me-1"></i> Simpan
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header text-black">
              <div class="d-flex justify-content-between align-items-center w-100">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
                  <i class="bi bi-plus-circle me-1"></i> Add
                </button>
              </div>
            </div>

            <div class="card-body" style="margin-top: 10px;">

              <!-- Table with stripped rows -->
              <div class="table-responsive" id="userTable">
                <table id="example1" class="table table-bordered table-striped text-center align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">#</th>
                      <th class="text-center">Username</th>
                      <th class="text-center">NIK</th>
                      <th class="text-center">Role</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Last Login</th>
                      <th class="text-center">Options</th>
                    </tr>
                  </thead>
                  <tbody class="table-border-bottom-0">

                    <?php $i = 1; ?>
                    <?php foreach ($users as $row) : ?>
                      <tr>
                        <td><?= $i ?></td>
                        <td><?= $row["username"]; ?></td>
                        <td><?= $row["nik_user"]; ?></td>
                        <td><?= $row["role_name"]; ?></td>
                        <td>
                          <?php if ($row["is_deleted"] == 0) : ?>
                            <span class="badge bg-success">Active</span>
                          <?php else : ?>
                            <span class="badge bg-danger">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td><?= $row['last_login'] ? $row['last_login'] : '<span class="text-muted">Belum pernah login</span>'; ?></td>
                        <td>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" id="dropdownMenu<?= $row['id_user']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                              <li>
                                <a class="dropdown-item editUserBtn"
                                  href="#"
                                  data-bs-toggle="modal"
                                  data-bs-target="#editUserModal"
                                  data-id="<?= $row['id_user']; ?>"
                                  data-username="<?= $row['username']; ?>"
                                  data-nik="<?= $row['nik_user']; ?>"
                                  data-role_id="<?= $row['role_id']; ?>">
                                  <i class="bi bi-pencil me-2"></i> Edit
                                </a>
                                <form action="./../config/function.php" method="post" onsubmit="return confirm('Yakin ingin hapus user ini?');">
                                  <input type="hidden" name="id_user" value="<?= htmlspecialchars($row['id_user']); ?>">
                                  <button type="submit" name="remove-user" class="dropdown-item text-danger">
                                    <i class="bi bi-trash me-2"></i> Remove
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

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">

          <!-- Header -->
          <div class="modal-header text-black" style="background-color: DDDAD0;">
            <h5 class="modal-title d-flex align-items-center" id="editUserModalLabel">
              <i class="bi bi-people me-2"></i> Edit Data User
            </h5>
            <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">
              <div class="row g-3">
                <input type="hidden" name="id_user" id="edit_id_user">
                <input type="hidden" name="updated_by" id="updated_by" value="<?= htmlspecialchars($username) ?>">

                <div class="col-md-6">
                  <label for="username" class="form-label">Username</label>
                  <input type="text" name="username" id="edit_username" class="form-control">
                </div>

                <div class="col-md-6">
                  <label for="nik_user" class="form-label">NIK</label>
                  <input type="text" name="nik_user" id="edit_nik_user" class="form-control">
                </div>

                <div class="col-md-6">
                  <label for="role_id" class="form-label">Role</label>
                  <select name="role_id" id="edit_role_id" class="form-select" required>
                    <option value="">Pilih role</option>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="password" class="form-label">Password</label>
                  <input type="text" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tidak diubah">
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="update-user">
                <i class="bi bi-check-circle me-1"></i> Update
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>


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
        // "buttons": ["copy", "excel"]
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

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const editButtons = document.querySelectorAll(".editUserBtn");

      editButtons.forEach(btn => {
        btn.addEventListener("click", function() {
          // Ambil data dari atribut
          const id = this.getAttribute("data-id");
          const username = this.getAttribute("data-username");
          const nik = this.getAttribute("data-nik");
          const role_id = this.getAttribute("data-role_id");

          // Isi data ke modal
          document.getElementById("edit_id_user").value = id;
          document.getElementById("edit_username").value = username;
          document.getElementById("edit_nik_user").value = nik;
          document.getElementById("edit_role_id").value = role_id;

          // Kosongkan password (supaya aman)
          document.getElementById("edit_password").value = "";
        });
      });
    });
  </script>


</body>

</html>