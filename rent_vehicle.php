<?php
session_start();
require_once __DIR__ . '/Includes/config.php';
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/stripe.php';
$need_login = !isset($_SESSION['User_ID']);
$APP_BASE = 'http://localhost/ceylon';

use Stripe\Stripe;
use Stripe\Checkout\Session;

$flash = $_SESSION['rv_flash'] ?? null;
unset($_SESSION['rv_flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_session') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['User_ID'])) {
        echo json_encode(['ok' => false, 'message' => 'Please log in to continue.']);
        exit;
    }
    $data = [
        'Name' => $_POST['Name'] ?? '',
        'Email' => $_POST['Email'] ?? '',
        'NIC_or_Pass' => $_POST['NIC_or_Pass'] ?? '',
        'Phone_No' => $_POST['Phone_No'] ?? '',
        'Start_Location' => $_POST['Start_Location'] ?? '',
        'Start_Date' => $_POST['Start_Date'] ?? '',
        'End_Date' => $_POST['End_Date'] ?? '',
        'Vehicle_ID' => (int)($_POST['Vehicle_ID'] ?? 0),
        'Amount' => (float)($_POST['Amount'] ?? 1000)
    ];
    $_SESSION['rental_data'] = $data;
    try {
        Stripe::setApiKey(STRIPE_SECRET_KEY);
        $checkout = Session::create([
            'mode' => 'payment',
            'success_url' => $APP_BASE . '/save_rental.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $APP_BASE . '/save_rental.php?cancel=1',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'lkr',
                    'product_data' => [
                        'name' => 'Vehicle Rental - ID ' . $data['Vehicle_ID'],
                        'description' => 'From ' . $data['Start_Date'] . ' to ' . $data['End_Date']
                    ],
                    'unit_amount' => intval(round($data['Amount'] * 100))
                ],
                'quantity' => 1
            ]]
        ]);
        echo json_encode(['ok' => true, 'url' => $checkout->url]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
$categories = [
    'Tuk' => ['icon' => 'fa-solid fa-taxi', 'seating' => 3],
    'Bike' => ['icon' => 'fa-solid fa-motorcycle', 'seating' => 2],
    'Mini Car' => ['icon' => 'fa-solid fa-car-side', 'seating' => 4],
    'Car' => ['icon' => 'fa-solid fa-car', 'seating' => 5],
    'Mini Van' => ['icon' => 'fa-solid fa-van-shuttle', 'seating' => 7],
    'Van' => ['icon' => 'fa-solid fa-bus', 'seating' => 12],
];
$requested_start = $_GET['Start_Date'] ?? date('Y-m-d');
$requested_end = $_GET['End_Date'] ?? date('Y-m-d');
$vehicles = [];
foreach ($categories as $cat_name => $cat_data) {
    $cat_db = str_replace(' ', '_', $cat_name);
    $sql = "SELECT * FROM vehicle 
            WHERE Category='$cat_db' 
            AND Vehicle_ID NOT IN (
                SELECT Vehicle_ID FROM vehicle_rentals
                WHERE NOT (End_Date < '$requested_start' OR Start_Date > '$requested_end')
            )";
    $res = $conn->query($sql);
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    if ($row) $row['Category'] = $cat_db;
    $vehicles[$cat_name] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent a Vehicle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8fbff;
            font-family: 'Segoe UI', sans-serif
        }

        .about {
            padding: 60px 20px;
            text-align: center
        }

        .about h2 {
            font-weight: bold;
            margin-bottom: 20px
        }

        .about p {
            max-width: 800px;
            margin: auto;
            color: #555;
            line-height: 1.6
        }

        .card {
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: .2s;
            position: relative;
            padding: 20px;
            text-align: center
        }

        .card:hover {
            transform: translateY(-5px)
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            background: #e8f1ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            font-size: 28px;
            color: #007bff
        }

        .price-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #007bff;
            color: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: .9rem
        }

        .btn-book {
            border-radius: 30px;
            font-weight: 600
        }

        .date-filter {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 40px;
            margin-top: 20px
        }

        .date-filter input {
            border-radius: 30px;
            padding: 10px 20px;
            border: 1px solid #ddd
        }

        .date-filter button {
            border-radius: 30px;
            padding: 10px 25px;
            background: #0d6efd;
            color: #fff;
            border: none;
            transition: .3s
        }

        .date-filter button:hover {
            background: #0b5ed7
        }
    </style>
</head>

<body>
    <?php require __DIR__ . './Includes/Header.php'; ?>
    <section class="about">
        <h2>About Our Vehicle Rentals</h2>
        <p>We provide affordable and reliable vehicle rentals across Sri Lanka.</p>
    </section>
    <div class="container py-5">
        <h1 class="text-center mb-5 text-primary fw-bold">🚗 Rent a Vehicle</h1>
        <form method="GET" class="date-filter">
            <input type="date" name="Start_Date" value="<?= $requested_start ?>" required>
            <input type="date" name="End_Date" value="<?= $requested_end ?>" required>
            <button type="submit">Check Availability</button>
        </form>
        <div class="row g-4">
            <?php foreach ($categories as $cat_name => $cat_data): ?>
                <?php $v = $vehicles[$cat_name]; ?>
                <div class="col-md-4">
                    <div class="card">
                        <?php if ($v): ?>
                            <div class="price-badge">LKR <?= $v['Price_Per_Day'] ?>/day</div>
                        <?php endif; ?>
                        <div class="icon-circle"><i class="<?= $cat_data['icon'] ?>"></i></div>
                        <h4 class="mt-3"><?= $cat_name ?></h4>
                        <p><strong>Seating:</strong> <?= $cat_data['seating'] ?></p>
                        <?php if ($v): ?>
                            <button class="btn btn-primary btn-book mt-2" data-bs-toggle="modal" data-bs-target="#bookingModal"
                                data-vehicle-id="<?= (int)$v['Vehicle_ID'] ?>"
                                data-price="<?= (float)$v['Price_Per_Day'] ?>"
                                data-seating="<?= (int)$cat_data['seating'] ?>"
                                data-name="<?= htmlspecialchars($cat_name) ?>"
                                data-category="<?= htmlspecialchars($v['Category']) ?>">Book Now</button>
                        <?php else: ?>
                            <p class="text-danger mt-2">No <?= $cat_name ?> available</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="bookingForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Book Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="Category" id="modalCategory">
                    <input type="hidden" name="Vehicle_ID" id="modalVehicleID">
                    <input type="hidden" name="Start_Date" id="modalStart" value="<?= $requested_start ?>">
                    <input type="hidden" name="End_Date" id="modalEnd" value="<?= $requested_end ?>">
                    <input type="hidden" name="Amount" id="modalAmount">
                    <p><strong>Vehicle:</strong> <span id="modalVehicle"></span></p>
                    <p><strong>Price/Day:</strong> LKR <span id="modalPrice"></span></p>
                    <p><strong>Seating:</strong> <span id="modalSeating"></span></p>
                    <div class="mt-3">
                        <label class="form-label">Your Name</label>
                        <input type="text" class="form-control" name="Name" required>
                        <label class="form-label mt-2">Email</label>
                        <input type="email" class="form-control" name="Email" required>
                        <label class="form-label mt-2">NIC/Passport</label>
                        <input type="text" class="form-control" name="NIC_or_Pass" required>
                        <label class="form-label mt-2">Phone</label>
                        <input type="text" class="form-control" name="Phone_No" required>
                        <label class="form-label mt-2">Start Location</label>
                        <input type="text" class="form-control" name="Start_Location" id="startLocationInput" required>
                    </div>
                    <div id="formMessage" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Confirm Booking</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/Includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function daysBetween(start, end) {
            const s = new Date(start + 'T00:00:00');
            const e = new Date(end + 'T00:00:00');
            const d = Math.floor((e - s) / (1000 * 60 * 60 * 24));
            return d > 0 ? d : 1;
        }
        const bookingModal = document.getElementById('bookingModal');
        bookingModal.addEventListener('show.bs.modal', e => {
            const btn = e.relatedTarget;
            const price = parseFloat(btn.getAttribute('data-price')) || 0;
            document.getElementById('modalVehicle').textContent = btn.getAttribute('data-name');
            document.getElementById('modalPrice').textContent = price;
            document.getElementById('modalSeating').textContent = btn.getAttribute('data-seating');
            document.getElementById('modalVehicleID').value = btn.getAttribute('data-vehicle-id');
            document.getElementById('modalCategory').value = btn.getAttribute('data-category');
            const start = document.getElementById('modalStart').value;
            const end = document.getElementById('modalEnd').value;
            document.getElementById('modalAmount').value = (price * daysBetween(start, end)).toFixed(2);
            if (window.google && window.google.maps && window.google.maps.places && !document.getElementById('startLocationInput').dataset.acReady) {
                initPlaces();
            }
        });
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const data = new FormData(form);
            fetch('rent_vehicle.php?action=create_session', {
                method: 'POST',
                body: data
            }).then(res => res.json()).then(r => {
                if (r.ok && r.url) window.location = r.url;
                else Swal.fire({
                    icon: 'error',
                    title: 'Payment Error',
                    text: r.message || 'Please try again.'
                });
            }).catch(() => Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please try again.'
            }));
        });
        window.initPlaces = function() {
            const input = document.getElementById('startLocationInput');
            if (!input || input.dataset.acReady) return;
            const ac = new google.maps.places.Autocomplete(input, {});
            ac.addListener('place_changed', function() {
                const p = ac.getPlace();
                if (p && p.formatted_address) {
                    input.value = p.formatted_address;
                }
            });
            input.dataset.acReady = '1';
        };
        (function() {
            var s = document.createElement('script');
            s.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyCYCblZmBwFlc_NfpJoS5bMWP87Pm3wM9w&libraries=places&callback=initPlaces";
            s.async = true;
            s.defer = true;
            document.head.appendChild(s);
        })();
    </script>
    <?php if ($need_login): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Please log in to continue',
                    confirmButtonText: 'Go to Login'
                }).then(() => {
                    window.location = 'login.php';
                });
            });
        </script>
    <?php endif; ?>
    <?php if ($flash === 'success'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful',
                    text: 'Vehicle rental booked successfully.',
                    timer: 2500,
                    showConfirmButton: false
                });
            });
        </script>
    <?php elseif ($flash === 'cancel'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Payment Cancelled',
                    text: 'No payment was made.'
                });
            });
        </script>
    <?php elseif ($flash === 'error'): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: 'error',
                    title: 'Booking Failed',
                    text: 'Could not save your booking. Please try again.'
                });
            });
        </script>
    <?php endif; ?>
</body>

</html>