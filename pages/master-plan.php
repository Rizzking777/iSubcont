<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('master_plan'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

// Query ambil data aktual dari tbl_plan_detail
$today = date("Y-m-d");

// Query ambil data aktual hanya untuk hari ini
$sql = "
  SELECT 
    d.id_cycle_detail,
    d.plan_date,          -- tanggal aktual per row
    d.status,
    d.plan_cycle,
    n.ncvs,
    p.id_cycle
  FROM tbl_plan_detail d
  JOIN tbl_plan p ON d.id_cycle = p.id_cycle
  JOIN tbl_ncvs n ON d.id_ncvs = n.id_ncvs
  WHERE d.plan_date = ?
  ORDER BY n.ncvs ASC, d.id_cycle_detail ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result_plans = $stmt->get_result();

$plans = [];

if ($result_plans && $result_plans->num_rows > 0) {
  while ($row = $result_plans->fetch_assoc()) {
    $plan_date = $row['plan_date'];

    $plans[$plan_date]['header'] = [
      'plan_date' => $plan_date
    ];

    $plans[$plan_date]['details'][] = [
      'id_cycle_detail' => $row['id_cycle_detail'],
      'ncvs'            => $row['ncvs'],
      'plan_cycle'      => $row['plan_cycle'],
      'status'          => $row['status']
    ];
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

  <title>iSubcont - Master Plan Cycle</title>
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
  $page = 'master_plan';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Master Data Plan Cycle
      </h1>
    </div>

    <!-- Modal Tambah Plan Cycle -->
    <div class="modal fade" id="tambahPlanCycle" tabindex="-1" aria-labelledby="tambahPlanCycleLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

          <!-- Header -->
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center" id="tambahPlanCycleLabel">
              <i class="bi bi-clipboard-plus me-2"></i> Tambah Plan Cycle
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
                      <th class="text-center" style="width:45%;">NCVS <span class="text-danger">*</span></th>
                      <th class="text-center" style="width:35%;">Plan Cycle <span class="text-danger">*</span></th>
                      <th class="text-center" style="width:15%;">Action</th>
                    </tr>
                  </thead>
                  <tbody id="ncvsTable">
                    <tr>
                      <td class="text-center">1</td>
                      <td>
                        <select name="ncvs[]" class="form-select select2-ncvs" style="width:100%" required></select>

                        </select>
                      </td>
                      <td>
                        <input type="number" name="plan_cycle[]" class="form-control" placeholder="Input plan cycle" required>
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
              <button type="submit" class="btn btn-success" name="submit-plan">
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
                      <th class="text-center">NCVS</th>
                      <th class="text-center">Plan Cycle</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Options</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $i = 1; ?>
                    <?php foreach ($plans as $plan): ?>
                      <?php foreach ($plan['details'] as $detail): ?>
                        <tr>
                          <td><?= $i++; ?></td>
                          <td><?= htmlspecialchars($plan['header']['plan_date']); ?></td>
                          <td><?= htmlspecialchars($detail['ncvs']); ?></td>
                          <td><?= htmlspecialchars($detail['plan_cycle']); ?></td>
                          <td>
                            <div class="form-check form-switch d-flex justify-content-center">
                              <form action="./../config/function.php" method="post">
                                <input type="hidden" name="id_cycle_detail" value="<?= $detail['id_cycle_detail']; ?>">
                                <input type="hidden" name="toggle_plan_status" value="1">
                                <input
                                  class="form-check-input"
                                  type="checkbox"
                                  name="status"
                                  value="1"
                                  onchange="this.form.submit()"
                                  <?= $detail['status'] == 1 ? 'checked' : ''; ?>>
                              </form>
                            </div>

                          </td>
                          <td>
                            <div class="dropdown">
                              <button class="btn btn-sm btn-outline-primary" type="button" id="dropdownMenu<?= $row['id_cycle_detail']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end">
                                <!-- Edit -->
                                <li>
                                  <a class="dropdown-item editPlanBtn"
                                    href="#"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editPlanModal"
                                    data-id="<?= $detail['id_cycle_detail']; ?>"
                                    data-date="<?= htmlspecialchars($plan['header']['plan_date']); ?>"
                                    data-ncvs="<?= htmlspecialchars($detail['ncvs']); ?>"
                                    data-cycle="<?= htmlspecialchars($detail['plan_cycle']); ?>">
                                    <i class="bi bi-pencil me-2"></i> Edit
                                  </a>
                                </li>

                              </ul>
                            </div>
                          </td>

                        </tr>
                      <?php endforeach; ?>
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

    <!-- Modal Edit Plan -->
    <div class="modal fade" id="editPlanModal" tabindex="-1" aria-labelledby="editPlanModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">

          <!-- Header -->
          <div class="modal-header text-black" style="background-color: #DDDAD0;">
            <h5 class="modal-title" id="editPlanModalLabel">
              <i class="bi bi-clipboard-plus me-2"></i> Edit Plan Cycle
            </h5>
            <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">
              <!-- Hidden Inputs -->
              <input type="hidden" name="id_cycle_detail" id="edit_id">
              <input type="hidden" name="updated_by" id="updated_by" value="<?= htmlspecialchars($username) ?>">

              <!-- Fields -->
              <div class="mb-6">
                <label for="edit_date_plan" class="form-label">Date Plan</label>
                <input type="text" id="edit_date_plan" class="form-control" readonly>
              </div>

              <div class="mb-6">
                <label for="edit_ncvs" class="form-label">NCVS</label>
                <input type="text" id="edit_ncvs" class="form-control" readonly>
              </div>

              <div class="mb-6">
                <label for="edit_plan_cycle" class="form-label">Plan Cycle</label>
                <input type="number" name="plan_cycle" id="edit_plan_cycle" class="form-control" required>
              </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="update-plan">
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
      const editButtons = document.querySelectorAll(".editPlanBtn");

      editButtons.forEach(btn => {
        btn.addEventListener("click", function() {
          // Ambil data dari atribut tombol
          const id_cycle_detail = this.getAttribute("data-id");
          const date_plan = this.getAttribute("data-date");
          const ncvs = this.getAttribute("data-ncvs");
          const plan_cycle = this.getAttribute("data-cycle");

          // Isi ke form modal
          document.getElementById("edit_id").value = id_cycle_detail;
          document.getElementById("edit_date_plan").value = date_plan;
          document.getElementById("edit_ncvs").value = ncvs;
          document.getElementById("edit_plan_cycle").value = plan_cycle;
        });
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      let rowIdx = 1;

      // Function init select2 untuk dropdown ncvs
      function initSelect2(context) {
        $(context).find(".select2-ncvs").select2({
          dropdownParent: $("#tambahPlanCycle"),
          placeholder: "Please input min 1 character",
          minimumInputLength: 1,
          width: "100%",
          ajax: {
            url: "./../config/get_ncvs.php",
            dataType: "json",
            delay: 250,
            data: function(params) {
              return {
                search: params.term
              };
            },
            processResults: function(data) {
              return {
                results: data
              };
            },
          },
        });
      }

      // Fokus otomatis ke search select2
      $(document).on("select2:open", () => {
        document.querySelector(".select2-container--open .select2-search__field")?.focus();
      });

      // Init pertama (row default)
      initSelect2(document);

      // Add row
      document.getElementById("addRow").addEventListener("click", function() {
        rowIdx++;
        let table = document.querySelector("#ncvsTable");
        let newRow = document.createElement("tr");

        newRow.innerHTML = `
      <td class="text-center">${rowIdx}</td>
      <td>
        <select name="ncvs[]" class="form-select select2-ncvs" style="width:100%" required></select>
      </td>
      <td>
        <input type="number" name="plan_cycle[]" class="form-control" placeholder="Input plan cycle" required>
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm removeRow">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;

        table.appendChild(newRow);

        // Init select2 untuk row baru
        initSelect2(newRow);
      });

      // Remove row (kecuali row pertama)
      document.addEventListener("click", function(e) {
        if (e.target.closest(".removeRow")) {
          const row = e.target.closest("tr");
          const index = row.rowIndex; // posisi row di table
          if (index > 1) {
            row.remove();
          }
        }
      });

      // Hide tombol delete di row pertama
      document.querySelector("#ncvsTable tr:first-child .removeRow")?.remove();
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