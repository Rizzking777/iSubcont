<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/get_data_lot_basis_detail.php';

checkAuth('lot_basis'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order
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

  .cell-empty {
    background-color: #adb5bd !important;
    color: #FFFFFF !important;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Dashboards</title>
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

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'lot_basis';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details Lot Basis
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">

              <div class="container mt-4">

                <!-- Info Job Order -->
                <div class="row g-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Job Order</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['job_order'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Bucket</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['bucket'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_code'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Item</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_item'] ?>">
                  </div>
                </div>

                <div class="row g-2 mt-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Model</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['model'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Style</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['style'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">NCVS</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['ncvs'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Lot Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['status_lot'] ?>">
                  </div>
                </div>

                <hr>

                <p class="text-success mb-1">Description data per cell</p>
                <p class="text-success fs-4">
                  [ PLAN | IN SM SUBCONT | SEND WH | RETURN SM | OUT TO PROD ]
                </p>

                <?php foreach ($komponenList as $komponen): ?>

                  <?php
                  $kompId   = (int) $komponen['id'];
                  $kompName = $komponen['nama'];

                  $componentData = $tableData[$kompId] ?? [];
                  ?>

                  <h5 class="mt-4 fw-bold">
                    Komponen: <?= htmlspecialchars($kompName) ?>
                  </h5>

                  <div style="overflow-x:auto;">
                    <table class="table table-bordered text-center align-middle"
                      style="min-width: 1200px;">

                      <thead class="table-light">
                        <tr>
                          <th style="white-space:nowrap;">LOT</th>

                          <?php foreach ($officialSizes as $size): ?>
                            <th style="white-space:nowrap;">
                              <?= htmlspecialchars($size) ?>
                            </th>
                          <?php endforeach; ?>

                          <th style="white-space:nowrap;">Total</th>
                        </tr>
                      </thead>

                      <tbody>

                        <?php
                        $grandPlan     = 0;
                        $grandInSm     = 0;
                        $grandSendWh   = 0;
                        $grandReturnSm = 0;
                        $grandOutProd  = 0;
                        ?>

                        <?php foreach ($lots as $lot): ?>

                          <?php
                          $lotPlan     = 0;
                          $lotInSm     = 0;
                          $lotSendWh   = 0;
                          $lotReturnSm = 0;
                          $lotOutProd  = 0;
                          ?>

                          <tr>

                            <td class="fw-bold bg-light">
                              <?= htmlspecialchars($lot) ?>
                            </td>

                            <?php foreach ($officialSizes as $size): ?>

                              <?php
                              $d = $componentData[$lot][$size] ?? [
                                'plan'      => 0,
                                'in_sm'     => 0,
                                'send_wh'   => 0,
                                'return_sm' => 0,
                                'out_prod'  => 0
                              ];

                              $plan     = (float) $d['plan'];
                              $inSm     = (float) $d['in_sm'];
                              $sendWh   = (float) $d['send_wh'];
                              $returnSm = (float) $d['return_sm'];
                              $outProd  = (float) $d['out_prod'];

                              $isEmpty = (
                                $plan == 0 &&
                                $inSm == 0 &&
                                $sendWh == 0 &&
                                $returnSm == 0 &&
                                $outProd == 0
                              );

                              $inSmClass     = $inSm < 0 ? 'text-danger' : 'text-success';
                              $sendWhClass   = $sendWh < 0 ? 'text-danger' : 'text-success';
                              $returnSmClass = $returnSm < 0 ? 'text-danger' : 'text-success';
                              $outProdClass  = $outProd < 0 ? 'text-danger' : 'text-success';

                              $lotPlan     += $plan;
                              $lotInSm     += $inSm;
                              $lotSendWh   += $sendWh;
                              $lotReturnSm += $returnSm;
                              $lotOutProd  += $outProd;
                              ?>

                              <td class="<?= $isEmpty ? 'cell-empty' : '' ?>"
                                style="white-space:nowrap;">

                                <?php if (!$isEmpty): ?>

                                  <span class="text-success">
                                    <?= $plan ?>
                                  </span>
                                  |

                                  <span class="<?= $inSmClass ?>">
                                    <?= $inSm ?>
                                  </span>
                                  |

                                  <span class="<?= $sendWhClass ?>">
                                    <?= $sendWh ?>
                                  </span>
                                  |

                                  <span class="<?= $returnSmClass ?>">
                                    <?= $returnSm ?>
                                  </span>
                                  |

                                  <span class="<?= $outProdClass ?>">
                                    <?= $outProd ?>
                                  </span>

                                <?php endif; ?>

                              </td>

                            <?php endforeach; ?>

                            <?php
                            $grandPlan     += $lotPlan;
                            $grandInSm     += $lotInSm;
                            $grandSendWh   += $lotSendWh;
                            $grandReturnSm += $lotReturnSm;
                            $grandOutProd  += $lotOutProd;
                            ?>

                            <td class="fw-bold bg-light"
                              style="white-space:nowrap;">

                              <span class="text-success">
                                <?= $lotPlan ?>
                              </span>
                              |

                              <span class="<?= $lotInSm < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $lotInSm ?>
                              </span>
                              |

                              <span class="<?= $lotSendWh < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $lotSendWh ?>
                              </span>
                              |

                              <span class="<?= $lotReturnSm < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $lotReturnSm ?>
                              </span>
                              |

                              <span class="<?= $lotOutProd < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $lotOutProd ?>
                              </span>

                            </td>

                          </tr>

                        <?php endforeach; ?>

                        <!-- TOTAL -->
                        <tr class="fw-bold table-light">

                          <td>Total</td>

                          <?php foreach ($officialSizes as $size): ?>

                            <?php
                            $sumPlan     = 0;
                            $sumInSm     = 0;
                            $sumSendWh   = 0;
                            $sumReturnSm = 0;
                            $sumOutProd  = 0;

                            foreach ($componentData as $lot => $sizesData) {

                              $d = $sizesData[$size] ?? [
                                'plan'      => 0,
                                'in_sm'     => 0,
                                'send_wh'   => 0,
                                'return_sm' => 0,
                                'out_prod'  => 0
                              ];

                              $sumPlan     += (float) $d['plan'];
                              $sumInSm     += (float) $d['in_sm'];
                              $sumSendWh   += (float) $d['send_wh'];
                              $sumReturnSm += (float) $d['return_sm'];
                              $sumOutProd  += (float) $d['out_prod'];
                            }
                            ?>

                            <td style="white-space:nowrap;">

                              <span class="text-success">
                                <?= $sumPlan ?>
                              </span>
                              |

                              <span class="<?= $sumInSm < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $sumInSm ?>
                              </span>
                              |

                              <span class="<?= $sumSendWh < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $sumSendWh ?>
                              </span>
                              |

                              <span class="<?= $sumReturnSm < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $sumReturnSm ?>
                              </span>
                              |

                              <span class="<?= $sumOutProd < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= $sumOutProd ?>
                              </span>

                            </td>

                          <?php endforeach; ?>

                          <td style="white-space:nowrap;">

                            <span class="text-success">
                              <?= $grandPlan ?>
                            </span>
                            |

                            <span class="<?= $grandInSm < 0 ? 'text-danger' : 'text-success' ?>">
                              <?= $grandInSm ?>
                            </span>
                            |

                            <span class="<?= $grandSendWh < 0 ? 'text-danger' : 'text-success' ?>">
                              <?= $grandSendWh ?>
                            </span>
                            |

                            <span class="<?= $grandReturnSm < 0 ? 'text-danger' : 'text-success' ?>">
                              <?= $grandReturnSm ?>
                            </span>
                            |

                            <span class="<?= $grandOutProd < 0 ? 'text-danger' : 'text-success' ?>">
                              <?= $grandOutProd ?>
                            </span>

                          </td>

                        </tr>

                      </tbody>

                    </table>
                  </div>

                <?php endforeach; ?>

              </div>

            </div>

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

  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toastEl = document.getElementById('liveToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
          delay: 5000
        });
        toast.show();
      }
    });
  </script>

</body>

</html>