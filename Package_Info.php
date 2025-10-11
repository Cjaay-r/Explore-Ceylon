<?php
session_start();

$conn = new mysqli("localhost", "root", "", "explore_ceylon_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$packageId = 0;
if (isset($_GET['id'])) $packageId = (int)$_GET['id'];
elseif (isset($_POST['Package_ID'])) $packageId = (int)$_POST['Package_ID'];

$package = null;
$packageStmt = $conn->prepare("SELECT Package_ID, Name, Subtitle, Long_Des, DurationDays, Price, Root_img FROM packages WHERE Package_ID=?");
$packageStmt->bind_param("i", $packageId);
$packageStmt->execute();
$package = $packageStmt->get_result()->fetch_assoc();

$imageStmt = $conn->prepare("SELECT ImageUrl, AltText FROM packageimages WHERE Package_ID=?");
$imageStmt->bind_param("i", $packageId);
$imageStmt->execute();
$imageResult = $imageStmt->get_result();

$itineraryStmt = $conn->prepare("SELECT DayNumber, Location, Description FROM itinerary WHERE PackageID=? ORDER BY DayNumber ASC");
$itineraryStmt->bind_param("i", $packageId);
$itineraryStmt->execute();
$itineraryResult = $itineraryStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($package['Name'] ?? 'Package') ?> - Tour Package</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{font-family:Arial,sans-serif;background:#fff;color:#333}
    .container{margin:40px auto}
    h1{font-size:28px;margin-bottom:10px}
    p{line-height:1.6;text-align:justify}
    .gallery-wrapper{overflow:hidden;width:100%;margin:20px 0;position:relative}
    .gallery{display:flex;gap:20px;transition:transform .6s ease}
    .gallery-item{flex-shrink:0;width:calc((100% - 40px)/3);cursor:pointer}
    .gallery-item img{width:100%;height:200px;object-fit:cover;border-radius:6px;display:block}
    .arrow{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.5);color:#fff;border:none;font-size:24px;width:40px;height:40px;border-radius:50%;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center}
    .arrow-left{left:10px}
    .arrow-right{right:10px}
    .lightbox{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);align-items:center;justify-content:center;z-index:100}
    .lightbox img{max-width:90%;max-height:90%;border-radius:8px}
    .accordion-item{border:none;margin-bottom:10px;border-radius:4px;overflow:hidden}
    .accordion-header{background:#000;color:#fff;padding:12px;cursor:pointer;font-weight:bold;display:flex;align-items:center;justify-content:space-between}
    .accordion-header span{font-size:20px;transition:transform .3s ease}
    .accordion-content{max-height:0;overflow:hidden;padding:0 15px;background:#f9f9f9;line-height:1.6;border:1px solid #ddd;border-top:none;transition:max-height .4s ease;text-align:justify}
    .accordion-content h2{font-size:22px;font-weight:bold;padding:10px 0}
    .accordion-content h4{font-size:16px;padding-bottom:10px}
    .sidebar .card{border:1px solid #eee;padding:20px;margin-bottom:20px;box-shadow:0 2px 5px rgba(0,0,0,.05);border-radius:6px;text-align:center}
    .sidebar button{background:#0d6efd;border:none;padding:10px 20px;margin-top:10px;cursor:pointer;color:#fff;font-size:16px;border-radius:4px}
    .sidebar img{width:100%;border-radius:6px}
    .btn{background-color: #0d6efd; color: #ffffffff;}
  </style>
</head>
<body>
  <div class="container">
    <?php if (!$package): ?>
      <div class="alert alert-warning">Package not found.</div>
    <?php else: ?>
    <div class="row">
      <div class="col-lg-8 col-md-12 mb-4">
        <small><?= htmlspecialchars($package['Subtitle']) ?></small>
        <h1><?= htmlspecialchars($package['Name']) ?></h1>
        <p><?= nl2br(htmlspecialchars($package['Long_Des'])) ?></p>

        <div class="gallery-wrapper">
          <button class="arrow arrow-left">&#8249;</button>
          <button class="arrow arrow-right">&#8250;</button>
          <div class="gallery" id="gallery">
            <?php
            $images = [];
            while ($img = $imageResult->fetch_assoc()) $images[] = $img;
            $totalImages = count($images);
            $loopImages = $images;
            if ($totalImages > 3) $loopImages = array_merge($images, array_slice($images, 0, 3));
            foreach ($loopImages as $img):
            ?>
              <div class="gallery-item">
                <img src="<?= htmlspecialchars($img['ImageUrl']) ?>" alt="<?= htmlspecialchars($img['AltText']) ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="lightbox" id="lightbox">
          <img src="" alt="Zoomed Image">
        </div>

        <h2>Itinerary Overview</h2>
        <div class="accordion">
          <?php while ($row = $itineraryResult->fetch_assoc()): ?>
            <div class="accordion-item">
              <div class="accordion-header">Day <?= (int)$row['DayNumber'] ?> <span>+</span></div>
              <div class="accordion-content">
                <h2><?= htmlspecialchars($row['Location']) ?></h2>
                <h4><?= htmlspecialchars($row['Description']) ?></h4>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="col-lg-4 col-md-12">
        <div class="sidebar">
          <div class="card">
            <h3><?= htmlspecialchars($package['Name']) ?></h3>
            <p>Duration: <?= (int)$package['DurationDays'] ?> Days</p>
            <p class="fw-bold text-primary">Price: Rs.<?= number_format((float)$package['Price'], 2) ?></p>
            <a href="booking_package.php?id=<?= (int)$package['Package_ID'] ?>" class="btn">Book Now</a>
          </div>
          <div class="card">
            <img src="<?= htmlspecialchars($package['Root_img']) ?>" alt="Package Image" class="img-fluid">
          </div>
          <div class="card">
            <h3>100% Safe and Secure</h3>
            <p>Your bookings are 100% safe and secure with us, guaranteed.</p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const headers = document.querySelectorAll('.accordion-header');
    headers.forEach(header=>{
      header.addEventListener('click',()=>{
        const item=header.parentElement;
        const content=item.querySelector('.accordion-content');
        const symbol=header.querySelector('span');
        if(item.classList.contains('active')){
          content.style.maxHeight=content.scrollHeight+"px";
          setTimeout(()=>{content.style.maxHeight="0"},10);
          item.classList.remove('active');
          symbol.textContent="+";
        }else{
          item.classList.add('active');
          content.style.maxHeight=content.scrollHeight+"px";
          symbol.textContent="–";
        }
      });
    });

    const gallery=document.getElementById('gallery');
    const items=gallery?gallery.querySelectorAll('.gallery-item'):[];
    const total=items.length;
    let index=0;
    const leftArrow=document.querySelector('.arrow-left');
    const rightArrow=document.querySelector('.arrow-right');

    if(leftArrow&&rightArrow&&gallery){
      leftArrow.addEventListener('click',()=>{
        index=Math.max(0,index-1);
        gallery.style.transition="transform 0.6s ease";
        gallery.style.transform=`translateX(-${index*(100/3+20/3)}%)`;
      });

      rightArrow.addEventListener('click',()=>{
        index++;
        const maxIndex=total-3;
        gallery.style.transition="transform 0.6s ease";
        gallery.style.transform=`translateX(-${index*(100/3+20/3)}%)`;
        if(index>=maxIndex){
          setTimeout(()=>{
            gallery.style.transition="none";
            index=0;
            gallery.style.transform=`translateX(0)`;
          },610);
        }
      });

      const lightbox=document.getElementById('lightbox');
      const lightboxImg=lightbox?lightbox.querySelector('img'):null;
      items.forEach(item=>{
        item.addEventListener('click',()=>{
          const img=item.querySelector('img').src;
          if(lightboxImg){lightboxImg.src=img;}
          if(lightbox){lightbox.style.display='flex';}
        });
      });
      if(lightbox){
        lightbox.addEventListener('click',()=>{lightbox.style.display='none';});
      }
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
