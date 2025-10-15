<?php 
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 
require_once __DIR__ . '/Includes/config.php'; 
require_once __DIR__ . '/Includes/dbconnect.php'; 
require_once __DIR__ . '/Includes/auth.php'; 
require_once __DIR__ . '/Includes/stripe.php';

$APP_BASE = 'http://localhost/ceylon';

$flash = null;
$flashData = [];

use Stripe\Stripe;
use Stripe\Checkout\Session;

if (isset($_GET['payment']) && $_GET['payment'] === 'success' && isset($_GET['session_id']) && isset($_SESSION['ticket_data'])) {
    Stripe::setApiKey(STRIPE_SECRET_KEY);
    $session = Session::retrieve($_GET['session_id']);
    if ($session && isset($session->payment_status) && $session->payment_status === 'paid') {
        $d = $_SESSION['ticket_data'];
        $qr = uniqid('QR_');

        // Use the logged-in user's ID; validate it exists to satisfy FK constraint
        $userID = isset($_SESSION['User_ID']) ? (int)$_SESSION['User_ID'] : null;

        if ($userID === null) {
            $flash = 'dberror';
            $flashData = ['message' => 'You must be logged in to complete the booking.'];
            unset($_SESSION['ticket_data']);
        } else {
            $chk = $conn->prepare("SELECT 1 FROM user WHERE User_ID = ? LIMIT 1");
            $chk->bind_param("i", $userID);
            $chk->execute();
            $exists = $chk->get_result()->fetch_row();
            $chk->close();

            if (!$exists) {
                $flash = 'dberror';
                $flashData = ['message' => 'Account not found. Please sign in again.'];
                unset($_SESSION['ticket_data']);
            } else {
                $stmt = $conn->prepare("INSERT INTO tickets (Name, Contact_No, Destination, Category, No_Of_People, Purchased_Date, Valid_Date, Total_Price, Qr, User_ID) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param("ssssisdsi", $d['Name'], $d['Contact_No'], $d['Destination'], $d['Category'], $d['No_Of_People'], $d['Valid_Date'], $d['Total_Price'], $qr, $userID);
                if ($stmt->execute()) {
                    $stmt->close();
                    $flash = 'success';
                    $flashData = [
                        'Name'=>$d['Name'],
                        'Contact_No'=>$d['Contact_No'],
                        'Destination'=>$d['Destination'],
                        'Category'=>$d['Category'],
                        'No_Of_People'=>$d['No_Of_People'],
                        'Valid_Date'=>$d['Valid_Date'],
                        'Total_Price'=>$d['Total_Price'],
                        'Qr'=>$qr
                    ];
                } else {
                    $flash = 'dberror';
                    $flashData = ['message'=>$stmt->error];
                    $stmt->close();
                }
                unset($_SESSION['ticket_data']);
            }
        }
        
    } else {
        $flash = 'unpaid';
    }
} elseif (isset($_GET['payment']) && $_GET['payment'] === 'cancel') {
    unset($_SESSION['ticket_data']);
    $flash = 'cancel';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_session') {
    header('Content-Type: application/json');
    $name = $_POST['Name']; 
    $contact = $_POST['Contact_No']; 
    $destination = $_POST['Destination']; 
    $category = $_POST['Category']; 
    $numPeople = intval($_POST['No_Of_People']); 
    $arrivingDate = $_POST['Valid_Date']; 
    $totalPrice = floatval($_POST['Total_Price']); 

    $_SESSION['ticket_data'] = [
        'Name'=>$name,
        'Contact_No'=>$contact,
        'Destination'=>$destination,
        'Category'=>$category,
        'No_Of_People'=>$numPeople,
        'Valid_Date'=>$arrivingDate,
        'Total_Price'=>$totalPrice
    ];

    try {
        Stripe::setApiKey(STRIPE_SECRET_KEY);
        $checkout = Session::create([
            'mode' => 'payment',
            'success_url' => $APP_BASE . '/tickets.php?payment=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $APP_BASE . '/tickets.php?payment=cancel',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Ticket: ' . $destination,
                        'description' => $category . ' | People: ' . $numPeople
                    ],
                    'unit_amount' => intval(round($totalPrice * 100))
                ],
                'quantity' => 1
            ]]
        ]);
        echo json_encode(['ok'=>true,'url'=>$checkout->url]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tickets - Explore Ceylon</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
body { 
    background: #f8f9fa; 
    font-family: 'Poppins', sans-serif; 
}
.ticket-card { 
    transition: transform 0.3s ease, box-shadow 0.3s ease; 
}
.ticket-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
}
.btn-book { 
    background-color: #198754; 
    color: #fff; 
    border-radius: 30px; 
    transition: background 0.3s; 
}
.btn-book:hover { 
    background-color: #157347; 
}
.card-img-top {
    height: 210px;
    object-fit: cover;
}
</style>
</head>
<body>
<?php require __DIR__ . './Includes/Header.php'; ?>
<div class="container py-5">
<h1 class="text-center fw-bold text-success mb-4">Explore Ceylon Tickets</h1>
<div class="text-center mb-4">
<select id="categoryFilter" class="form-select w-auto mx-auto shadow-sm">
<option value="All">All Categories</option>
<option value="Cultural">Cultural</option>
<option value="Nature">Nature</option>
<option value="Wildlife">Wildlife</option>
<option value="zoos">Zoos</option>
<option value="Museum">Museums</option>
</select>
</div>

<div id="ticketsContainer" class="row g-4">
<?php
$destinations = [
    ['name'=>'Temple Of Tooth Relic','category'=>'Cultural','image'=>'Temple Of Tooth Relic.jpg','open'=>'5:00 AM - 8:00 PM','price'=>6],
    ['name'=>'Sigiriya','category'=>'Cultural','image'=>'Sigiriya.jpeg','open'=>'5:00 AM - 8:00 PM','price'=>10],
    ['name'=>'Dambulla Cave Temple','category'=>'Cultural','image'=>'Dambulla Cave Temple.jpeg','open'=>'5:00 AM - 8:00 PM','price'=>7],
    ['name'=>'Polonnaruwa Ancient City','category'=>'Cultural','image'=>'Polonnaruwa Ancient City.jpeg','open'=>'5:00 AM - 8:00 PM','price'=>12],
    ['name'=>'Anuradhapura Sacred Area','category'=>'Cultural','image'=>'Anuradhapura Sacred Area.jpg','open'=>'5:00 AM - 8:00 PM','price'=>12],
    ['name'=>'Embekka Devalaya','category'=>'Cultural','image'=>'Embekka Devalaya.jpg','open'=>'5:00 AM - 8:00 PM','price'=>5],
    ['name'=>'Nagadeepa Purana Viharaya','category'=>'Cultural','image'=>'Nagadeepa Purana Viharaya.jpg','open'=>'5:00 AM - 8:00 PM','price'=>5],
    ['name'=>'Dehiwala National Zoo','category'=>'zoos','image'=>'Dehiwala National Zoo.jpg','open'=>'8:00 AM - 6:00 PM','price'=>5.90],
    ['name'=>'Pinnawala Zoo','category'=>'zoos','image'=>'Pinnawala Zoo.jpeg','open'=>'8:00 AM - 6:00 PM','price'=>3.40],
    ['name'=>'Yala National Park','category'=>'Wildlife','image'=>'Yala National Park.jpg','open'=>'6:00 AM - 5:00 PM','price'=>6.60],
    ['name'=>'Wilpattu Park','category'=>'Wildlife','image'=>'Wilpattu Park.webp','open'=>'6:00 AM - 5:00 PM','price'=>8.25],
    ['name'=>'Minneriya National Park','category'=>'Wildlife','image'=>'Minneriya National Park.jpg','open'=>'6:00 AM - 5:00 PM','price'=>8.25],
    ['name'=>'Kaudulla National Park','category'=>'Wildlife','image'=>'Kaudulla National Park.jpg','open'=>'6:00 AM - 5:00 PM','price'=>8],
    ['name'=>'Udawalawe National Park','category'=>'Wildlife','image'=>'Udawalawe National Park.jpg','open'=>'6:00 AM - 5:00 PM','price'=>7.50],
    ['name'=>'Sinharaja Forest Reserve','category'=>'Nature','image'=>'Sinharaja Forest Reserve.jpeg','open'=>'7:00 AM - 5:30 PM','price'=>10],
    ['name'=>'Mirissa Whale Watching','category'=>'Nature','image'=>'Mirissa Whale Watching.jpg','open'=>'7:00 AM - 5:30 PM','price'=>5],
    ['name'=>'Trincomalee Whale Watching','category'=>'Nature','image'=>'Trincomalee Whale Watching.jpg','open'=>'7:00 AM - 5:30 PM','price'=>5],
    ['name'=>'Kalpitiya Whale Watching','category'=>'Nature','image'=>'Kalpitiya Whale Watching.jpg','open'=>'7:00 AM - 5:30 PM','price'=>5.50],
    ['name'=>'Royal Botanic Gardens, Peradeniya','category'=>'Nature','image'=>'Royal Botanic Gardens, Peradeniya.jpg','open'=>'7:00 AM - 5:30 PM','price'=>6.95],
    ['name'=>'Hakgala Botanical Garden','category'=>'Nature','image'=>'Hakgala Botanical Garden.jpg','open'=>'7:00 AM - 5:30 PM','price'=>6.90],
    ['name'=>'Mirijjawila Dry Zone Botanical Garden','category'=>'Nature','image'=>'Mirijjawila Dry Zone Botanical Garden.jpeg','open'=>'7:00 AM - 5:30 PM','price'=>6],
    ['name'=>'Seethawaka Botanical Garden','category'=>'Nature','image'=>'Seethawaka Botanical Garden.jpg','open'=>'7:00 AM - 5:30 PM','price'=>6],
    ['name'=>'Henarathgoda Botanical Garden','category'=>'Nature','image'=>'Henarathgoda Botanical Garden.jpeg','open'=>'7:00 AM - 5:30 PM','price'=>6],
    ['name'=>'Colombo National Museum','category'=>'Museum','image'=>'Colombo National Museum.jpeg','open'=>'9:00 AM - 5:00 PM','price'=>10],
    ['name'=>'Temple of the Tooth Museum','category'=>'Museum','image'=>'Temple of the Tooth Museum.jpg','open'=>'9:00 AM - 5:00 PM','price'=>1],
    ['name'=>'Mask Museum, Ambalangoda','category'=>'Museum','image'=>'Mask Museum, Ambalangoda.jpg','open'=>'9:00 AM - 5:00 PM','price'=>4.95],
    ['name'=>'Polonnaruwa Archaeological Museum','category'=>'Museum','image'=>'Polonnaruwa Archaeological Museum.jpg','open'=>'9:00 AM - 5:00 PM','price'=>11.55],
    ['name'=>'Galle National Museum','category'=>'Museum','image'=>'Galle National Museum.webp','open'=>'9:00 AM - 5:00 PM','price'=>12]

];

foreach ($destinations as $dest) {
    echo "
    <div class='col-md-4 col-lg-3 ticket-card' data-category='{$dest['category']}'>
        <div class='card h-100 shadow-sm'>
            <img src='Images/{$dest['image']}' class='card-img-top rounded-top' alt='{$dest['name']}'>
            <div class='card-body text-center'>
                <h5 class='card-title fw-bold text-dark'>{$dest['name']}</h5>
                <p class='text-muted mb-1'><strong>Open:</strong> {$dest['open']}</p>
                <p class='text-success fw-semibold mb-2'>$ {$dest['price']}</p>
                <button class='btn btn-book bookBtn' data-bs-toggle='modal' data-bs-target='#bookingModal' data-name='{$dest['name']}' data-category='{$dest['category']}' data-price='{$dest['price']}'>Book Ticket</button>
            </div>
        </div>
    </div>
    ";
}
?>
</div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content p-3 border-0 shadow">
<div class="modal-header border-0">
<h5 class="modal-title fw-bold text-success">Book Your Ticket</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<form id="bookingForm">
<input type="hidden" id="destination" name="Destination">
<input type="hidden" id="category" name="Category">
<div class="mb-3">
<label class="form-label">Your Name</label>
<input type="text" class="form-control" id="name" name="Name" required>
</div>
<div class="mb-3">
<label class="form-label">Contact Number</label>
<input type="text" class="form-control" id="contact" name="Contact_No" required>
</div>
<div class="mb-3">
<label class="form-label">Number of People</label>
<input type="number" class="form-control" id="numPeople" name="No_Of_People" min="1" required>
</div>
<div class="mb-3">
<label class="form-label">Arriving Date</label>
<input type="date" class="form-control" id="arrivingDate" name="Valid_Date" required>
</div>
<div class="mb-3">
<label class="form-label">Total Price ($)</label>
<input type="text" class="form-control" id="totalPrice" name="Total_Price" readonly>
</div>
<button type="submit" class="btn btn-book w-100 py-2 fw-semibold">Confirm Booking</button>
</form>
</div>
</div>
</div>
</div>
<?php include __DIR__ . '/Includes/footer.php'; ?>

<script>
$(document).ready(function(){
let basePrice = 0;
$('.bookBtn').on('click', function(){
    $('#destination').val($(this).data('name'));
    $('#category').val($(this).data('category'));
    basePrice = $(this).data('price');
    $('#numPeople').val('');
    $('#totalPrice').val('');
    $('#name').val('');
    $('#contact').val('');
});
$('#numPeople').on('input', function(){
    const num = $(this).val();
    $('#totalPrice').val(num * basePrice);
});
$('#bookingForm').on('submit', function(e){
    e.preventDefault();
    $.ajax({
        url: 'tickets.php?action=create_session',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(r){
            if(r.ok && r.url){
                window.location = r.url;
            } else {
                Swal.fire({icon:'error',title:'Payment Error',text:r.message||'Please try again.'});
            }
        },
        error: function(){
            Swal.fire({icon:'error',title:'Network Error',text:'Please try again.'});
        }
    });
});
$('#categoryFilter').on('change', function(){
    const val = $(this).val();
    $('.ticket-card').show();
    if(val!=='All') $('.ticket-card').not("[data-category='"+val+"']").hide();
});
});
function generatePDF(name, contact, destination, category, people, total, date){
const { jsPDF } = window.jspdf;
const doc = new jsPDF();
doc.setFillColor(34, 139, 34);
doc.rect(0, 0, 210, 25, 'F');
doc.setFontSize(18);
doc.setTextColor(255, 255, 255);
doc.setFont('helvetica', 'bold');
doc.text('Explore Ceylon Ticket Receipt', 105, 17, { align: 'center' });
doc.setFontSize(12);
doc.setTextColor(0, 0, 0);
doc.setFont('helvetica', 'normal');
let startY = 40;
const lineHeight = 10;
doc.text(`Name: ${name}`, 20, startY);
doc.text(`Contact: ${contact}`, 20, startY + lineHeight);
doc.text(`Destination: ${destination}`, 20, startY + lineHeight*2);
doc.text(`Category: ${category}`, 20, startY + lineHeight*3);
doc.text(`No. of People: ${people}`, 20, startY + lineHeight*4);
doc.text(`Arriving Date: ${date}`, 20, startY + lineHeight*5);
doc.text(`Total Price: $ ${total}`, 20, startY + lineHeight*6);
const qrDiv = document.createElement('div');
new QRCode(qrDiv, destination+'-'+Date.now());
setTimeout(()=>{
    const qrCanvas = qrDiv.querySelector('canvas');
    const qrData = qrCanvas.toDataURL('image/png');
    doc.addImage(qrData, 'PNG', 150, startY, 50, 50);
    doc.setFillColor(34, 139, 34);
    doc.rect(0, 280, 210, 17, 'F');
    doc.setFontSize(11);
    doc.setTextColor(255, 255, 255);
    doc.text('Thank you for booking with Explore Ceylon!', 105, 291, { align: 'center' });
    doc.save('Ticket_Receipt.pdf');
}, 500);
}
</script>

<?php if ($flash === 'success'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        icon:'success',
        title:'Payment Successful',
        text:'Your ticket has been booked.',
        showConfirmButton:false,
        timer:2000
    }).then(()=>{
        generatePDF(
            <?= json_encode($flashData['Name']) ?>,
            <?= json_encode($flashData['Contact_No']) ?>,
            <?= json_encode($flashData['Destination']) ?>,
            <?= json_encode($flashData['Category']) ?>,
            <?= json_encode($flashData['No_Of_People']) ?>,
            <?= json_encode($flashData['Total_Price']) ?>,
            <?= json_encode($flashData['Valid_Date']) ?>
        );
    });
});
</script>
<?php elseif ($flash === 'cancel'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({icon:'info',title:'Payment Cancelled',text:'No charge was made.'});
});
</script>
<?php elseif ($flash === 'unpaid'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({icon:'error',title:'Payment Not Completed',text:'Please try again.'});
});
</script>
<?php elseif ($flash === 'dberror'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({icon:'error',title:'Save Failed',text:<?= json_encode($flashData['message'] ?? 'Please contact support.') ?>});
});
</script>
<?php endif; ?>

</body>
</html>
