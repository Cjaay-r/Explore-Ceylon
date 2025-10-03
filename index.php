<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['User_ID'])) {
    header("Location: login.php"); // your login/register page
    exit;
}

// ---- DB (for packages in s5 only) ----
require_once 'config/database.php';
try {
    $__pkg_conn = getMySQLiConnection();
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}
$__pkg_sql = "SELECT Package_ID, Name, Root_img, DurationDays, Description, Price FROM packages ORDER BY Package_ID ASC";
$__pkg_result = $__pkg_conn->query($__pkg_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Ceylon</title>
    <link rel="stylesheet" href="Styles/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>
<body>
  <!-- Main header -->
   <?php
   include("Includes/header.php");
?>
  <main>
    <section class="s1">
  <p class="subheading">Get unforgettable pleasure with us</p>
  <h1>Welcome To <br> Explore Ceylon</h1>
  <div class="cta-buttons">
    <a href="#" class="explore-btn">Explore Tours <i class="fa fa-arrow-right"></i></a>
    <a href="#" class="our-services-btn">Our Services <i class="fa fa-arrow-right"></i></a>
  </div>

  <!-- Destination Filter Bar -->
  <div class="destination-filter">
    <div class="filter-item">
      <i class="fa fa-map-marker"></i>
      <select>
        <option>Sigiriya</option>
        <option>Polonnaruwa</option>
        <option>Temple of tooth relic</option>
        <option>Bundala</option>
      </select>
    </div>
    <div class="filter-item">
      <i class="fa fa-bicycle"></i>
      <select>
        <option>Activity</option>
        <option>Adventure</option>
        <option>Relaxation</option>
        <option>Culture</option>
      </select>
    </div>
    <div class="filter-item">
      <i class="fa fa-clock-o"></i>
      <select>
        <option>3 Days - 6 Days</option>
        <option>7 Days - 10 Days</option>
        <option>10+ Days</option>
      </select>
    </div>
    <div class="filter-item">
      <i class="fa fa-money"></i>
      <select>
        <option>LKR20,000 - LKR50,000</option>
        <option>LKR9,000 - LKR10,000</option>
        <option>LKR10,000+</option>
      </select>
    </div>
    <button class="search-btn">Search</button>
  </div>
</section>


    <section class="information" id="infor">
  <p class="sub-title">GET TO KNOW US</p>
  <h1 class="main-title">Why We're Your Perfect Travel Partner</h1>
  <p class="description">
    Lorem ipsum dolor sit amet consectetur. Vitae blandit eu etiam urna odio risus maecenas mauris.
  </p>

  <div class="features">
    <div class="feature-item">
      <div class="icon-circle">
        <i class="fa fa-commenting-o"></i>
      </div>
      <h3>25 million +</h3>
      <p>There are many variations of passages have suffered</p>
    </div>

    <div class="feature-item">
      <div class="icon-circle">
        <i class="fa fa-smile-o"></i>
      </div>
      <h3>No hidden fees</h3>
      <p>There are many variations of passages have suffered</p>
    </div>

    <div class="feature-item">
      <div class="icon-circle">
        <i class="fa fa-check-circle-o"></i>
      </div>
      <h3>Booking flexibility</h3>
      <p>There are many variations of passages have suffered</p>
    </div>

    <div class="feature-item">
      <div class="icon-circle">
        <i class="fa fa-bus"></i>
      </div>
      <h3>Included transfers</h3>
      <p>There are many variations of passages have suffered</p>
    </div>
  </div>
</section>


    <section class="s3">
    <h1>Plan your dream adventure in paradise today</h1>
</section>

<section class="s4">
    <div class="tour-header">
      <p class="sub-text">Wonderful Place For You</p>
      <h2 class="main-title">Tour Categories</h2>
    </div>

    <div class="carousel-container">
      <div class="tour-carousel">
        <div class="tour-card">
          <img src="img/safari.jpg" alt="Wildlife">
          <div class="tour-card-content">
            <h3>Wildlife</h3>
            <p>See More</p>
          </div>
        </div>
        <div class="tour-card">
          <img src="img/Walking.jpg" alt="Walking">
          <div class="tour-card-content">
            <h3>Walking</h3>
            <p>See More</p>
          </div>
        </div>
        <div class="tour-card">
          <img src="img/Cruises.jpg" alt="Cruises">
          <div class="tour-card-content">
            <h3>Cruises</h3>
            <p>See More</p>
          </div>
        </div>
        <div class="tour-card">
          <img src="img/Hiking.jpg" alt="Hiking">
          <div class="tour-card-content">
            <h3>Hiking</h3>
            <p>See More</p>
          </div>
        </div>
        <div class="tour-card">
          <img src="img/rain ride.jpg" alt="Airbirds">
          <div class="tour-card-content">
            <h3>Rain rides</h3>
            <p>See More</p>
          </div>
        </div>
      </div>
    </div>
  </section>

<!-- ===== s5: Packages from DB with horizontal slideshow ===== -->
<section class="s5">
    <h1>Our Packages</h1>
    <p class="subheading">Best Recommended Places</p>
    <p class="description">Discover Sri Lanka’s most popular destinations with our curated packages. From cultural wonders to tropical beaches, enjoy unforgettable experiences.</p>

    <!-- scoped styles for card bits used only in s5 -->
    <style>
      /* keep original look, but force consistent card height */
      .s5 .package-card{
        display:flex;
        flex-direction:column;
        height:420px;              /* fixed, consistent height */
        box-sizing:border-box;
      }
      .s5 .pkg-img-wrap{position:relative;height:200px;overflow:hidden;border-radius:10px}
      .s5 .pkg-img-wrap img{width:100%;height:100%;object-fit:cover;display:block}
      .s5 .pkg-badge{position:absolute;top:10px;left:10px;background:#0d6efd;color:#fff;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600}
      .s5 .pkg-title{
        color:#0b3c5d;font-size:18px;margin:10px 0;
        display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; /* clamp to 2 lines */
        min-height:44px;           /* reserve space for 2 lines */
        line-height:1.2;
      }
      .s5 .pkg-desc{color:#333;font-size:14px;margin:5px 0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
      .s5 .pkg-price{color:#0b3c5d;font-weight:bold;margin-top:auto} /* push price & button to bottom */
      .s5 .pkg-btn{display:block;margin-top:10px;background:#0d6efd;color:#fff;text-align:center;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600}
      .s5 .pkg-btn:hover{opacity:.9}
    </style>

    <div class="package-carousel" id="pkgCarousel">
      <?php if ($__pkg_result && $__pkg_result->num_rows > 0): ?>
        <?php while($row = $__pkg_result->fetch_assoc()): ?>
          <div class="package-card">
            <div class="pkg-img-wrap">
              <img src="<?= htmlspecialchars($row['Root_img']) ?>" alt="<?= htmlspecialchars($row['Name']) ?>">
              <span class="pkg-badge"><?= (int)$row['DurationDays'] ?> Days</span>
            </div>
            <h3 class="pkg-title"><?= htmlspecialchars($row['Name']) ?></h3>
            <p class="pkg-desc"><?= htmlspecialchars($row['Description']) ?></p>
            <p class="pkg-price">From $<?= number_format((float)$row['Price'], 2) ?></p>
            <a class="pkg-btn" href="Package_Info.php?id=<?= (int)$row['Package_ID'] ?>">Explore more</a>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="package-card">
          <h3 class="pkg-title">No packages available</h3>
          <p class="pkg-desc">Please check back later.</p>
          <p class="pkg-price"></p>
          <a class="pkg-btn" href="#">Explore more</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="carousel-dots" id="pkgDots"></div>
</section>

<section class="s6"></section>
<section class="s6"></section>

<?php
   include("footer.php");
   if (isset($__pkg_conn)) { $__pkg_conn->close(); }
?>

  </main>

  <script>
  // Scroll animation for information features
  const featureItems = document.querySelectorAll(".feature-item");

  const observerInfo = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("show");
      }
    });
  }, { threshold: 0.2 });

  featureItems.forEach(item => observerInfo.observe(item));
</script>

<script>
  // Scroll animation for s1 section filter items
  const s1Items = document.querySelectorAll(".s1 .filter-item, .s1 .search-btn");

  const observerS1 = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("show");
      }
    });
  }, { threshold: 0.2 });

  s1Items.forEach(item => observerS1.observe(item));
</script>

<script>
  // ===== Simple horizontal slideshow for the packages (keeps your markup) =====
  (function(){
    const container = document.getElementById('pkgCarousel');
    const dotsWrap  = document.getElementById('pkgDots');
    if(!container) return;

    const cards = container.querySelectorAll('.package-card');
    if(cards.length === 0) return;

    function metrics(){
      const gap = parseFloat(getComputedStyle(container).columnGap || getComputedStyle(container).gap) || 0;
      const card = cards[0];
      const cardWidth = card.getBoundingClientRect().width;
      const step = cardWidth + gap;
      const visible = Math.max(1, Math.floor((container.clientWidth + gap) / step));
      const pages = Math.max(1, Math.ceil(cards.length / visible));
      return {gap, step, visible, pages};
    }

    let page = 0;
    let M = metrics();

    function renderDots(){
      dotsWrap.innerHTML = '';
      for(let i=0;i<M.pages;i++){
        const d = document.createElement('span');
        d.className = 'dot' + (i===page ? ' active' : '');
        d.addEventListener('click', ()=>goTo(i));
        dotsWrap.appendChild(d);
      }
    }

    function goTo(p){
      page = (p + M.pages) % M.pages;
      container.scrollTo({ left: page * (M.step * M.visible), behavior: 'smooth' });
      updateDots();
    }

    function updateDots(){
      dotsWrap.querySelectorAll('.dot').forEach((el, i)=> el.classList.toggle('active', i===page));
    }

    // autoplay (pause on hover)
    let timer = setInterval(()=>goTo(page+1), 4000);
    container.addEventListener('mouseenter', ()=>{ clearInterval(timer); });
    container.addEventListener('mouseleave', ()=>{ timer = setInterval(()=>goTo(page+1), 4000); });

    window.addEventListener('resize', ()=>{
      M = metrics();
      page = 0;
      renderDots();
      goTo(0);
    });

    M = metrics();
    renderDots();
  })();
</script>
</body>
</html>
