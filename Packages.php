<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "explore_ceylon_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch packages
$sql = "SELECT Package_ID, Name, Root_img, DurationDays, Description, Price 
        FROM packages ORDER BY Package_ID ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sri Lanka Tourism Packages</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f9f9f9;
    }
    .hero {
      background: url('https://www.srilanka.travel/image/cache/catalog/banner1-1600x700.jpg') no-repeat center center/cover;
      height: 60vh;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      text-align: center;
      position: relative;
    }
    .hero::before {
      content: "";
      position: absolute;
      top:0; left:0; right:0; bottom:0;
      background: rgba(0,0,0,0.4);
    }
    .hero h1 {
      position: relative;
      z-index: 1;
      font-size: 3rem;
      font-weight: bold;
    }
    .package-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      transition: transform 0.3s;
      overflow: hidden;
    }
    .package-card:hover {
      transform: translateY(-8px);
    }
    .price {
      font-size: 1.25rem;
      color: #0d6efd;
      font-weight: bold;
    }
    .image-container {
      position: relative;
    }
    .image-container img {
      width: 100%;
      height: 220px; /* fixed height */
      object-fit: cover; /* ensures proper cropping */
    }
    .days-badge {
      position: absolute;
      top: 10px;
      left: 10px;
      background: rgba(13, 110, 253, 0.9);
      color: #fff;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
    }
    /* make all cards same height */
    .card-body {
      display: flex;
      flex-direction: column;
    }
    .card-body .btn {
      margin-top: auto;
    }
  </style>
</head>
<body>

  <!-- Hero Section -->
  <section class="hero">
    <h1>Explore Sri Lanka with Our Tourism Packages</h1>
  </section>

  <!-- Packages Section -->
  <div class="container py-5">
    <div class="text-center mb-5">
      <h2 class="fw-bold text-primary">Popular Packages</h2>
      <p class="text-muted">Discover the beauty of Sri Lanka with carefully curated travel experiences</p>
    </div>
    
    <div class="row g-4">
      <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="col-md-4 d-flex">
            <div class="card package-card h-100 w-100">
              <div class="image-container">
                <img src="<?= htmlspecialchars($row['Root_img']) ?>" class="card-img-top" alt="<?= htmlspecialchars($row['Name']) ?>">
                <span class="days-badge"><?= $row['DurationDays'] ?> Days</span>
              </div>
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($row['Name']) ?></h5>
                <p class="card-text"><?= htmlspecialchars($row['Description']) ?></p>
                <p class="price">From Rs.<?= number_format($row['Price'], 2) ?></p>
                <!-- Updated link -->
                <a href="Package_Info.php?id=<?= $row['Package_ID'] ?>" class="btn btn-primary w-100">Explore more</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center">No packages available.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-primary text-white text-center py-3">
    <p class="mb-0">&copy; 2025 Sri Lanka Tourism. All Rights Reserved.</p>
^  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
