<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';


$packages = [];
$pkg_sql = "SELECT Package_ID, Name, Subtitle, Root_img, DurationDays, Description, Price FROM packages ORDER BY Package_ID ASC";
if ($pkg_res = $conn->query($pkg_sql)) {
    while ($row = $pkg_res->fetch_assoc()) {
        $packages[] = $row;
    }
    $pkg_res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Explore Ceylon</title>
  <link rel="stylesheet" href="Styles/index.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
</head>
<body>

  <?php include __DIR__ . '/Includes/header.php'; ?>

  <main>
    <section class="s1">
      <p class="subheading">Get unforgettable pleasure with us</p>
      <h1>Welcome To <br> Explore Ceylon</h1>
      <div class="cta-buttons">
        <a href="Packages.php" class="explore-btn">Explore Tours <i class="fa fa-arrow-right"></i></a>
        <a href="Services.php" class="our-services-btn">Our Services <i class="fa fa-arrow-right"></i></a>
      </div>

      <div class="destination-filter">
        <div class="filter-item">
          <i class="fa fa-map-marker"></i>
          <select id="pkgSelect">
            <option value="">Select Package</option>
            <?php foreach ($packages as $p): ?>
              <option value="<?php echo (int)$p['Package_ID']; ?>">
                <?php echo htmlspecialchars($p['Name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-item">
          <i class="fa fa-bicycle"></i>
          <input id="pkgSubtitle" type="text" placeholder="Title" readonly />
        </div>
        <div class="filter-item">
          <i class="fa fa-clock-o"></i>
          <input id="pkgDuration" type="text" placeholder="Duration" readonly />
        </div>
        <div class="filter-item">
          <i class="fa fa-money"></i>
          <input id="pkgPrice" type="text" placeholder="Price" readonly />
        </div>
        <button class="search-btn" id="pkgSearchBtn">Search</button>
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
          <div class="icon-circle"><i class="fa fa-commenting-o"></i></div>
          <h3>25 million +</h3>
          <p>There are many variations of passages have suffered</p>
        </div>

        <div class="feature-item">
          <div class="icon-circle"><i class="fa fa-smile-o"></i></div>
          <h3>No hidden fees</h3>
          <p>There are many variations of passages have suffered</p>
        </div>

        <div class="feature-item">
          <div class="icon-circle"><i class="fa fa-check-circle-o"></i></div>
          <h3>Booking flexibility</h3>
          <p>There are many variations of passages have suffered</p>
        </div>

        <div class="feature-item">
          <div class="icon-circle"><i class="fa fa-bus"></i></div>
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
            <img src="Images/Cultural Tours.jpeg" alt="Cultural">
            <div class="tour-card-content">
              <h3>🧭 Cultural Tours</h3>
              <p>Historical sites, ancient cities, temples, local traditions.</p>
            </div>
          </div>
          <div class="tour-card">
            <img src="Images/Adventure Tours.webp" alt="Adventure">
            <div class="tour-card-content">
              <h3>🏞 Adventure Tours</h3>
              <p>Hiking, rafting, surfing, rock climbing, wildlife safaris.</p>
            </div>
          </div>
          <div class="tour-card">
            <img src="Images/Nature & Wildlife.jpg" alt="Nature & Wildlife">
            <div class="tour-card-content">
              <h3>🌿 Nature & Wildlife</h3>
              <p>National parks, safaris, birdwatching, rainforests.</p>
            </div>
          </div>
          <div class="tour-card">
            <img src="Images/Beach & Coastal Tour.jpg" alt="Beach & Coastal">
            <div class="tour-card-content">
              <h3>🌊 Beach & Coastal Tours</h3>
              <p>Relaxation, water sports, snorkeling, whale watching.</p>
            </div>
          </div>
          <div class="tour-card">
            <img src="Images/Train ride.jpg" alt="Hill Country">
            <div class="tour-card-content">
              <h3>🚂 Scenic & Hill Country</h3>
              <p>Tea plantations, waterfalls, scenic train rides, cool climates.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="s5">
      <h1>Our Packages</h1>
      <p class="subheading">Best Recommended Places</p>
      <p class="description">Discover Sri Lanka’s most popular destinations with our curated packages. From cultural wonders to tropical beaches, enjoy unforgettable experiences.</p>

      <div class="packages-wrapper">
        <div class="packages-rail" id="packagesRail" aria-label="Package list">
          <?php if (!empty($packages)): ?>
            <?php foreach ($packages as $p): ?>
              <article class="package-card" role="group" aria-roledescription="slide">
                <div class="package-media">
                  <img src="<?php echo htmlspecialchars($p['Root_img']); ?>" alt="<?php echo htmlspecialchars($p['Name']); ?>"/>
                  <span class="badge"><?php echo (int)$p['DurationDays']; ?> Days</span>
                </div>

                <div class="package-body">
                  <h3 class="package-title"><?php echo htmlspecialchars($p['Name']); ?></h3>
                  <p class="package-desc">
                    <?php
                      $desc = trim((string)$p['Description']);
                      echo htmlspecialchars(mb_strimwidth($desc, 0, 110, '…', 'UTF-8'));
                    ?>
                  </p>
                  <p class="package-price">From <span>
                    $USD.<?php echo number_format((float)$p['Price'], 2); ?>
                  </span></p>
                </div>

                <a class="pkg-btn" href="Package_Info.php?id=<?php echo (int)$p['Package_ID']; ?>">
                  Explore more
                </a>
              </article>
            <?php endforeach; ?>
          <?php else: ?>
            <article class="package-card">
              <div class="package-media empty"></div>
              <div class="package-body">
                <h3 class="package-title">No packages available</h3>
                <p class="package-desc">Please check back later.</p>
                <p class="package-price">&nbsp;</p>
              </div>
              <span class="pkg-btn disabled">Explore more</span>
            </article>
          <?php endif; ?>
        </div>
      </div>

      <div class="carousel-dots" id="pkgDots" hidden></div>
    </section>


    <?php include __DIR__ . '/Includes/footer.php'; ?>
  </main>
<?php include __DIR__ . '/Includes/message.php'; ?>
  <script>
    const featureItems = document.querySelectorAll(".feature-item");
    const observerInfo = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add("show"); });
    }, { threshold: 0.2 });
    featureItems.forEach(i => observerInfo.observe(i));

    const s1Items = document.querySelectorAll(".s1 .filter-item, .s1 .search-btn");
    const observerS1 = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add("show"); });
    }, { threshold: 0.2 });
    s1Items.forEach(i => observerS1.observe(i));
  </script>

  <script>
    (function() {
      const rail = document.getElementById('packagesRail');
      if (!rail) return;

      const dotsEl = document.getElementById('pkgDots');
      const GAP = 24;
      let cardWidth = 0;
      let timer = null;
      let index = 0;
      let cards = [];

      function refresh() {
        cards = [...rail.querySelectorAll('.package-card')];
        if (!cards.length) return;

        cardWidth = cards[0].getBoundingClientRect().width + GAP;

        const overflow = rail.scrollWidth > rail.clientWidth + 1;
        dotsEl.hidden = !overflow;

        if (overflow) {
          dotsEl.innerHTML = '';
          const visibleCount = Math.max(1, Math.floor(rail.clientWidth / cardWidth));
          const maxIndex = Math.max(0, cards.length - visibleCount);
          for (let i = 0; i <= maxIndex; i++) {
            const dot = document.createElement('span');
            dot.className = 'dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', () => {
              index = i;
              rail.scrollTo({ left: i * cardWidth, behavior: 'smooth' });
              setActiveDot(i);
            });
            dotsEl.appendChild(dot);
          }
        }
        restart();
      }

      function setActiveDot(i) {
        const dots = dotsEl.querySelectorAll('.dot');
        dots.forEach((d, idx) => d.classList.toggle('active', idx === i));
      }

      function tick() {
        if (!cards.length) return;
        const visibleCount = Math.max(1, Math.floor(rail.clientWidth / cardWidth));
        const maxIndex = Math.max(0, cards.length - visibleCount);
        index = (index + 1) % (maxIndex + 1);
        rail.scrollTo({ left: index * cardWidth, behavior: 'smooth' });
        setActiveDot(index);
      }

      function restart() {
        clearInterval(timer);
        if (rail.scrollWidth > rail.clientWidth + 1) {
          timer = setInterval(tick, 3500);
        }
      }

      rail.addEventListener('mouseenter', () => clearInterval(timer));
      rail.addEventListener('mouseleave', restart);

      const ro = new ResizeObserver(refresh);
      ro.observe(rail);
      window.addEventListener('load', refresh);
    })();
  </script>

  <script>
    (function() {
      const pkgSelect = document.getElementById('pkgSelect');
      const subtitleEl = document.getElementById('pkgSubtitle');
      const durationEl = document.getElementById('pkgDuration');
      const priceEl = document.getElementById('pkgPrice');
      const searchBtn = document.getElementById('pkgSearchBtn');

      const data = <?php
        $reduced = array_map(function($p){
          return [
            'id' => (int)$p['Package_ID'],
            'name' => (string)$p['Name'],
            'subtitle' => isset($p['Subtitle']) ? (string)$p['Subtitle'] : '',
            'duration' => (int)$p['DurationDays'],
            'price' => (float)$p['Price']
          ];
        }, $packages);
        echo json_encode($reduced, JSON_UNESCAPED_UNICODE);
      ?>;

      const map = {};
      for (const p of data) map[p.id] = p;

      function fill(id) {
        if (!id || !map[id]) {
          subtitleEl.value = '';
          durationEl.value = '';
          priceEl.value = '';
          return;
        }
        const p = map[id];
        subtitleEl.value = p.subtitle || '';
        durationEl.value = p.duration ? (p.duration + ' Days') : '';
        priceEl.value = p.price !== undefined ? ('USD $' + Number(p.price).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})) : '';
      }

      pkgSelect.addEventListener('change', function() {
        const id = this.value ? parseInt(this.value, 10) : 0;
        fill(id);
      });

      searchBtn.addEventListener('click', function() {
        const id = pkgSelect.value ? parseInt(pkgSelect.value, 10) : 0;
        if (id) {
          window.location.href = 'Package_Info.php?id=' + id;
        }
      });
    })();
  </script>
  
</body>
</html>
