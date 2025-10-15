<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Our Services | Explore Ceylon</title>

<!-- Fonts, Bootstrap & AOS -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="Styles/Services.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
</head>

<body>

  <?php include __DIR__ . '/Includes/header.php'; ?>

<header data-aos="fade-down" class="Service-header">
    <h1>Our Services</h1>
    <p>Discover the best travel experiences and support we offer</p>
</header>



<!-- MAIN SERVICES -->
<section class="service-section">
    <h2 data-aos="zoom-in">Main Services</h2>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="service-card">
                    <img src="Images/Package Booking.jpg" alt="Package Booking">
                    <div class="service-content">
                        <h3>Package Booking</h3>
                        <p>Choose from our curated tour packages covering Sri Lanka’s top destinations, adventures, and cultural gems.</p>
                        <a href="Packages.php">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="service-card">
                    <img src="Images/Customize Trip Booking.jpg" alt="Customize Trip">
                    <div class="service-content">
                        <h3>Customize Trip Booking</h3>
                        <p>Build your own trip—pick destinations, travel style, and experiences for a one-of-a-kind adventure.</p>
                        <a href="CustomisedBookings.php">Plan Trip</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="service-card">
                    <img src="Images/Tickets for Destinations.jpg" alt="Tickets">
                    <div class="service-content">
                        <h3>Tickets for Destinations</h3>
                        <p>Book tickets for national parks, cultural landmarks, and iconic attractions in Sri Lanka.</p>
                        <a href="Tickets.php">Get Tickets</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="service-card">
                    <img src="Images/Rent a Vehicle.jpg" alt="Vehicle Rental">
                    <div class="service-content">
                        <h3>Rent a Vehicle</h3>
                        <p>Travel comfortably with car, van, or bike rentals—self-drive or with a professional driver.</p>
                        <a href="rent_vehicle.php">Rent Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- EXTERNAL SERVICES -->
<section class="service-section external">
    <h2 data-aos="zoom-in">External Services</h2>
    <div class="container">
        <div class="row justify-content-center">

            <div class="col-md-6" data-aos="fade-right" data-aos-delay="100">
                <div class="service-card">
                    <img src="Images/e-sim.jpg" alt="E-SIM">
                    <div class="service-content">
                        <h3>E-SIM Facilities</h3>
                        <p>Stay connected with reliable e-SIMs from leading providers in Sri Lanka.</p><br>
                        <a href="https://www.dialog.lk/esim" target="_blank">Dialog</a>&nbsp;&nbsp;&nbsp;&nbsp;
                        <a href="https://www.mobitel.lk/esim" target="_blank">Mobitel</a><br><br>
                        <a href="https://www.airtel.lk/esim" target="_blank">Airtel</a>&nbsp;&nbsp;&nbsp;&nbsp;
                        <a href="https://www.hutch.lk/esim" target="_blank">Hutch</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6" data-aos="fade-right" data-aos-delay="200">
                <div class="service-card">
                    <img src="Images/sl Railway.jpeg" alt="Train Booking">
                    <div class="service-content">
                        <h3>Train Booking</h3>
                        <p>Reserve seats for scenic train rides through Sri Lanka’s lush hill country and coastal routes.</p>
                        <a href="https://seatreservation.railway.gov.lk" target="_blank">Book Train (SL GOV)</a><br><br>
                        <a href="https://seatreservation.railway.gov.lk" target="_blank">Book Train</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- IMMIGRATION DEPARTMENT CARD -->
        <div class="emergency-card" data-aos="fade-up" data-aos-delay="250">
            <img src="Images/immigration.png" alt="Department of Immigration and Emigration">
            <div class="service-content">
                <h3>Department of Immigration and Emigration (Sri Lanka)</h3>
                <p style="text-align:center;">For visa services, passport inquiries, and travel regulations.</p>
                <div style="text-align:center;">
                    <a href="https://www.immigration.gov.lk" target="_blank" class="btn btn-success" style="border-radius:25px; background:#3ddc97; border:none; padding:10px 25px; font-weight:600;">Visit Official Website</a>
                </div>
            </div>
        </div>

        <!-- EMERGENCY CONTACTS CARD -->
        <div class="emergency-card" data-aos="fade-up" data-aos-delay="300">
            <img src="Images/emergency-contacts.jpg" alt="Emergency Numbers">
            <div class="service-content">
                <h3>Emergency Contacts (Sri Lanka)</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Emergency Service</th>
                            <th>Telephone Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Police Emergency Hotline</td><td>118 / 119</td></tr>
                        <tr><td>Ambulance / Fire & Rescue</td><td>110</td></tr>
                        <tr><td>Accident Service - General Hospital Colombo</td><td>011-2691111</td></tr>
                        <tr><td>Tourist Police</td><td>011-2421052</td></tr>
                        <tr><td>Police Emergency</td><td>011-2433333</td></tr>
                        <tr><td>Government Information Center</td><td>1919</td></tr>
                        <tr><td>Report Crimes</td><td>011-2691500</td></tr>
                        <tr><td>Emergency Police Mobile Squad</td><td>011-5717171</td></tr>
                        <tr><td>Fire & Ambulance Service</td><td>011-2422222</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/Includes/footer.php'; ?>

<!-- Bootstrap JS + AOS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 1000, once: true });
</script>
</body>
</html>
