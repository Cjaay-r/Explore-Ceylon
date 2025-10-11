<?php
// rent_vehicle.php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "explore_ceylon_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("DB Connection Failed: " . $conn->connect_error);
}

// Vehicle categories with icons and seating
$categories = [
    'Tuk' => ['icon' => 'fa-solid fa-taxi', 'seating' => 3],
    'Bike' => ['icon' => 'fa-solid fa-motorcycle', 'seating' => 2],
    'Mini Car' => ['icon' => 'fa-solid fa-car-side', 'seating' => 4],
    'Car' => ['icon' => 'fa-solid fa-car', 'seating' => 5],
    'Mini Van' => ['icon' => 'fa-solid fa-van-shuttle', 'seating' => 7],
    'Van' => ['icon' => 'fa-solid fa-bus', 'seating' => 12],
];

// Get requested date range
$requested_start = isset($_GET['Start_Date']) ? $_GET['Start_Date'] : date('Y-m-d');
$requested_end   = isset($_GET['End_Date']) ? $_GET['End_Date'] : date('Y-m-d');

// Fetch available vehicles per category
$vehicles = [];
foreach ($categories as $cat_name => $cat_data) {
    $cat_db = str_replace(' ', '_', $cat_name); // DB uses underscores

    $sql = "SELECT * FROM vehicle 
            WHERE Category='$cat_db' 
            AND Vehicle_ID NOT IN (
                SELECT Vehicle_ID FROM vehicle_rentals
                WHERE NOT (End_Date < '$requested_start' OR Start_Date > '$requested_end')
            )";
    $res = $conn->query($sql);

    if ($res && $res->num_rows > 0) {
        $vehicles[$cat_name] = $res->fetch_assoc(); // for price display
        $vehicles[$cat_name]['available'] = true;
        $vehicles[$cat_name]['Category'] = $cat_db;
    } else {
        $vehicles[$cat_name] = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent a Vehicle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fbff;
            font-family: 'Segoe UI', sans-serif;
        }

        .about {
            padding: 60px 20px;
            text-align: center;
        }

        .about h2 {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .about p {
            max-width: 800px;
            margin: auto;
            color: #555;
            line-height: 1.6;
        }

        .card {
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: 0.2s;
            position: relative;
            padding: 20px;
            text-align: center;
        }

        .card:hover {
            transform: translateY(-5px);
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
            color: #007bff;
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
            font-size: 0.9rem;
        }

        .btn-book {
            border-radius: 30px;
            font-weight: 600;
        }

        .date-filter {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 40px;
            margin-top: 20px;
        }

        .date-filter input {
            border-radius: 30px;
            padding: 10px 20px;
            border: 1px solid #ddd;
        }

        .date-filter button {
            border-radius: 30px;
            padding: 10px 25px;
            background: #0d6efd;
            color: #fff;
            border: none;
            transition: 0.3s;
        }

        .date-filter button:hover {
            background: #0b5ed7;
        }
    </style>
</head>

<body>

    <section class="about">
        <h2>About Our Vehicle Rentals</h2>
        <p>We provide affordable and reliable vehicle rentals across Sri Lanka. Whether you are looking for a quick ride on a bike, the classic tuk-tuk experience, or a comfortable van for your family trip, we have the right vehicle for you.</p>
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
                <div class="col-md-4">
                    <div class="card">
                        <?php if ($vehicles[$cat_name]): ?>
                            <div class="price-badge">LKR <?= $vehicles[$cat_name]['Price_Per_Day'] ?>/day</div>
                        <?php endif; ?>
                        <div class="icon-circle"><i class="<?= $cat_data['icon'] ?>"></i></div>
                        <h4 class="mt-3"><?= $cat_name ?></h4>
                        <p><strong>Seating:</strong> <?= $cat_data['seating'] ?></p>
                        <?php if ($vehicles[$cat_name]): ?>
                            <button class="btn btn-primary btn-book mt-2" data-bs-toggle="modal" data-bs-target="#bookingModal"
                                data-vehicle="<?= $cat_name ?>"
                                data-price="LKR <?= $vehicles[$cat_name]['Price_Per_Day'] ?>"
                                data-seating="<?= $cat_data['seating'] ?>"
                                data-category="<?= $vehicles[$cat_name]['Category'] ?>">Book Now</button>
                        <?php else: ?>
                            <p class="text-danger mt-2">No <?= $cat_name ?> available</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="bookingForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Book Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="Category" id="modalCategory">
                    <input type="hidden" name="Start_Date" value="<?= $requested_start ?>">
                    <input type="hidden" name="End_Date" value="<?= $requested_end ?>">
                    <p><strong>Vehicle:</strong> <span id="modalVehicle"></span></p>
                    <p><strong>Price/Day:</strong> <span id="modalPrice"></span></p>
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
                        <input type="text" class="form-control" name="Start_Location" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const bookingModal = document.getElementById('bookingModal');
        bookingModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('modalVehicle').textContent = button.getAttribute('data-vehicle');
            document.getElementById('modalPrice').textContent = button.getAttribute('data-price');
            document.getElementById('modalSeating').textContent = button.getAttribute('data-seating');
            document.getElementById('modalCategory').value = button.getAttribute('data-category');
            document.getElementById('formMessage').innerHTML = "";
        });

        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('save_rental.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const msgBox = document.getElementById('formMessage');
                    msgBox.innerHTML = `<div class="alert ${data.status === 'success' ? 'alert-success' : 'alert-danger'}">${data.message}</div>`;
                    if (data.status === 'success') {
                        this.reset();
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('formMessage').innerHTML = `<div class="alert alert-danger">Something went wrong!</div>`;
                });
        });
    </script>
</body>

</html>