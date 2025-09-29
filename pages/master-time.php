<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('master_time'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

$today = date('Y-m-d');

$sql = "SELECT *
        FROM tbl_time
        WHERE date_plan = ?
        ORDER BY start_hour ASC, hour ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$times = [];
while ($row = $result->fetch_assoc()) {
  $times[] = $row;
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

  .select2-container .select2-selection--single {
    height: 38px !important;
    /* tinggi sama dengan .form-control */
    padding: 6px 12px;
    border: 1px solid #ced4da;
    /* sama kayak input bootstrap */
    border-radius: 0.375rem;
    /* rounded-2 */
  }

  .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 24px;
    /* biar teks di tengah */
  }

  .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
    /* posisinya sejajar */
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Master Time</title>
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
  $page = 'master_time';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Master Data Time
      </h1>
    </div>

    <!-- Modal Tambah Plan Cycle -->
    <div class="modal fade" id="tambahPlanCycle" tabindex="-1" aria-labelledby="tambahPlanCycleLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

          <!-- Header -->
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center" id="tambahPlanCycleLabel">
              <i class="bi bi-alarm me-2"></i> Tambah Time
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">
              <input type="hidden" name="updated_by" value="<?= htmlspecialchars($username) ?>">

              <!-- Range tanggal -->
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                  <input type="date" name="start_date" id="start_date" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                  <input type="date" name="end_date" id="end_date" class="form-control" required>
                </div>
              </div>

              <!-- Tombol Add Row -->
              <div class="mb-3">
                <button type="button" class="btn btn-secondary" id="addRow">
                  <i class="bi bi-plus-circle me-1"></i> Add Row
                </button>
              </div>

              <!-- Tabel NCVS -->
              <div class="table-responsive">
                <table class="table table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center" style="width:5%;">No</th>
                      <th class="text-center" style="width:25%;">Hour <span class="text-danger">*</span></th>
                      <th class="text-center" style="width:25%;">Start Hour <span class="text-danger">*</span></th>
                      <th class="text-center" style="width:25%;">End Hour <span class="text-danger">*</span></th>
                      <th class="text-center" style="width:15%;">Action</th>
                    </tr>
                  </thead>
                  <tbody id="ncvsTable">
                    <tr>
                      <td class="text-center">1</td>
                      <td>
                        <input type="number" name="hour[]" class="form-control" placeholder="Input hour" required>
                        </select>
                      </td>
                      <td>
                        <input type="time" name="start_hour[]" class="form-control" required>
                      </td>
                      <td>
                        <input type="time" name="end_hour[]" class="form-control" required>
                      </td>
                      <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger removeRow">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="submit-time">
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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahPlanCycle">
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
                      <th class="text-center">Date Plan</th>
                      <th class="text-center">Hour</th>
                      <th class="text-center">Start Hour</th>
                      <th class="text-center">End Hour</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Options</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $i = 1; ?>
                    <?php foreach ($times as $time): ?>
                      <tr>
                        <td><?= $i++; ?></td>
                        <td><?= htmlspecialchars($time['date_plan']); ?></td>
                        <td><?= htmlspecialchars($time['hour']); ?></td>
                        <td><?= htmlspecialchars($time['start_hour']); ?></td>
                        <td><?= htmlspecialchars($time['end_hour']); ?></td>
                        <td>
                          <div class="form-check form-switch d-flex justify-content-center">
                            <form action="./../config/function.php" method="post">
                              <input type="hidden" name="id_time" value="<?= $time['id_time']; ?>">
                              <input type="hidden" name="toggle_time_status" value="1">
                              <input
                                class="form-check-input"
                                type="checkbox"
                                name="status"
                                value="1"
                                onchange="this.form.submit()"
                                <?= $time['status'] == 1 ? 'checked' : ''; ?>>
                            </form>
                          </div>
                        </td>
                        <td>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" id="dropdownMenu<?= $time['id_time']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                              <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                              <!-- Edit -->
                              <li>
                                <a class="dropdown-item editTimeBtn"
                                  href="#"
                                  data-bs-toggle="modal"
                                  data-bs-target="#editTimeModal"
                                  data-id="<?= $time['id_time']; ?>"
                                  data-date="<?= htmlspecialchars($time['date_plan']); ?>"
                                  data-hour="<?= htmlspecialchars($time['hour']); ?>"
                                  data-start="<?= htmlspecialchars($time['start_hour']); ?>"
                                  data-end="<?= htmlspecialchars($time['end_hour']); ?>">
                                  <i class="bi bi-pencil me-2"></i> Edit
                                </a>
                              </li>

                            </ul>
                          </div>
                        </td>
                      </tr>
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

    <!-- Modal Edit Time -->
    <div class="modal fade" id="editTimeModal" tabindex="-1" aria-labelledby="editTimeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <!-- Header -->
          <div class="modal-header text-black" style="background-color: #DDDAD0;">
            <h5 class="modal-title d-flex align-items-center" id="editTimeModalLabel">
              <i class="bi bi-alarm me-2"></i> Edit Time
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">
              <!-- Hidden Inputs -->
              <input type="hidden" name="id_time" id="edit_time_id">
              <input type="hidden" name="updated_by" value="<?= htmlspecialchars($username) ?>">

              <!-- Fields -->
              <div class="mb-3">
                <label for="edit_date_plan" class="form-label">Date Plan</label>
                <input type="text" id="edit_date_plan" class="form-control" readonly>
              </div>

              <div class="mb-3">
                <label for="edit_hour" class="form-label">Hour</label>
                <input type="number" id="edit_hour" class="form-control" readonly>
              </div>

              <div class="mb-3">
                <label for="edit_start_hour" class="form-label">Start Hour</label>
                <input type="time" name="start_hour" id="edit_start_hour" class="form-control" required>
              </div>

              <div class="mb-3">
                <label for="edit_end_hour" class="form-label">End Hour</label>
                <input type="time" name="end_hour" id="edit_end_hour" class="form-control" required>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="update-time">
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
      const editButtons = document.querySelectorAll(".editTimeBtn");

      editButtons.forEach(btn => {
        btn.addEventListener("click", function() {
          // Ambil data dari atribut tombol
          const id_time = this.getAttribute("data-id");
          const date_plan = this.getAttribute("data-date");
          const hour = this.getAttribute("data-hour");
          const start_hour = this.getAttribute("data-start");
          const end_hour = this.getAttribute("data-end");

          // Isi ke form modal
          document.getElementById("edit_time_id").value = id_time;
          document.getElementById("edit_date_plan").value = date_plan;
          document.getElementById("edit_hour").value = hour;
          document.getElementById("edit_start_hour").value = start_hour;
          document.getElementById("edit_end_hour").value = end_hour;
        });
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      let rowIdx = 1; // index nomor baris

      const tableBody = document.getElementById("ncvsTable");
      const addRowBtn = document.getElementById("addRow");

      // Hide tombol delete di row pertama
      const firstDeleteBtn = tableBody.querySelector(".removeRow");
      if (firstDeleteBtn) firstDeleteBtn.style.display = "none";

      // Add row baru
      addRowBtn.addEventListener("click", function() {
        rowIdx++;
        const newRow = document.createElement("tr");
        newRow.innerHTML = `
      <td class="text-center">${rowIdx}</td>
      <td>
        <input type="number" name="hour[]" class="form-control" placeholder="Input hour" required>
      </td>
      <td>
        <input type="time" name="start_hour[]" class="form-control" required>
      </td>
      <td>
        <input type="time" name="end_hour[]" class="form-control" required>
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm removeRow">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;
        tableBody.appendChild(newRow);
      });

      // Remove row tambahan
      tableBody.addEventListener("click", function(e) {
        if (e.target.closest(".removeRow")) {
          const row = e.target.closest("tr");
          // Pastikan baris pertama tidak terhapus
          if (row.rowIndex > 0) {
            row.remove();
            // update nomor urut
            Array.from(tableBody.rows).forEach((r, i) => r.cells[0].textContent = i + 1);
            rowIdx = tableBody.rows.length;
          }
        }
      });
    });
  </script>

  <script>
    $(document).on("change", ".toggle-status", function() {
      let id_detail = $(this).data("id");
      let newStatus = $(this).is(":checked") ? 1 : 0;

      $.ajax({
        url: "./../config/function.php",
        type: "POST",
        data: {
          toggle_plan_status: true,
          id_cycle_detail: id_detail,
          status: newStatus
        },
        success: function(response) {
          try {
            let res = JSON.parse(response);
            if (res.success) {
              Swal.fire({
                icon: "success",
                title: "Berhasil!",
                text: res.message,
                timer: 1500,
                showConfirmButton: false
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "Gagal!",
                text: res.message
              });
            }
          } catch (e) {
            console.error("Invalid JSON:", response);
          }
        },
        error: function() {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Terjadi kesalahan koneksi."
          });
        }
      });
    });
  </script>

</body>

</html>