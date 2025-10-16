<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';

/* Fetch guides + phone/profile + languages */
$guides = [];
$sql = "
  SELECT
    g.Guide_ID,
    g.F_Name,
    g.L_Name,
    g.Description,
    g.Price_per_Day,
    g.Rating,
    g.Status,
    u.Phone_No,
    u.User_Profile,
    GROUP_CONCAT(DISTINCT l.Language ORDER BY l.Language SEPARATOR ', ') AS Languages
  FROM guide g
  LEFT JOIN user u ON u.User_ID = g.User_ID
  LEFT JOIN language l ON l.Guide_ID = g.Guide_ID
  GROUP BY
    g.Guide_ID, g.F_Name, g.L_Name, g.Description, g.Price_per_Day, g.Rating, g.Status, u.Phone_No, u.User_Profile
  ORDER BY g.Rating DESC, g.Guide_ID ASC
";
if ($res = $conn->query($sql)) {
  while ($row = $res->fetch_assoc()) {
    $guides[] = $row;
  }
  $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - Explore Ceylon</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
  <link rel="stylesheet" href="Styles/about.css">
</head>

<body>

  <?php include __DIR__ . '/Includes/header.php'; ?>

  <section class="Topic">
    <h1>About Us</h1>
    <p>Your journey begins here – Discover. Experience. Remember.</p>
  </section>

  <section>
    <div class="content">
      <div class="slide-hidden text-slide">
        <h2>Who We Are</h2>
        <p class="contenttxt">
          We are Explore Ceylon, a team passionate about showcasing the beauty of Sri Lanka to the world.
          Our journey began with the vision of creating a trustworthy digital tourism platform that connects
          travelers with authentic Sri Lankan experiences. As locals, we understand the challenges tourists face,
          such as scams, misinformation, and language barriers. That’s why we built a user-friendly web solution
          that goes beyond traditional travel guides—helping you explore both iconic landmarks and hidden gems with
          confidence.
        </p>
      </div>
      <img src="Images/Train ride.jpg" alt="Travel Image" class="slide-hidden img-slide">
    </div>
  </section>

  <section>
    <div class="content">
      <img src="Images/Beach & Coastal Tour.jpg" alt="Beach" class="slide-hidden img-slide">
      <div class="slide-hidden text-slide">
        <h2>Our Mission</h2>
        <p class="contenttxt">
          Our mission is simple: to make travel in Sri Lanka seamless, safe, and memorable.
          We strive to empower travelers with accurate, up-to-date information, reliable booking options,
          and personalized trip planning. By integrating destinations, transport, ticketing, and trip tracking
          into one platform, we eliminate confusion and reduce the chances of tourists being misled.
          Above all, we are committed to promoting sustainable tourism while building trust between visitors
          and local communities.
        </p>
      </div>
    </div>
  </section>

  <section>
    <div class="content">
      <div class="slide-hidden text-slide">
        <h2>Why Choose Us</h2>
        <p class="contenttxt">
          ✅ Reliable Information – Avoid scams and misguidance with verified travel details. <br>
          ✅ All-in-One Platform – Explore destinations, book transport, plan trips, and track your journey effortlessly.
          <br>
          ✅ Local Expertise – Designed by a team who truly understands Sri Lanka’s culture, history, and traveler
          needs.<br>
          ✅ Personalized Experience – Custom trip planners, category-based browsing, and nearby attraction suggestions
          tailored to you.<br>
          ✅ Sustainable Tourism – We encourage responsible travel that benefits both tourists and local communities.
        </p>
      </div>
      <img src="Images/Adventure About.jpg" alt="Adventure" class="slide-hidden img-slide">
    </div>
  </section>

  <section class="team-section">
    <h2>Meet Our Team</h2>
    <div class="team-cards">
      <div class="team-card">
        <img src="Images/chamod.png" alt="Chamod">
        <h3>Chamod Ranaweera</h3>
        <p>Project Lead and Developer. Passionate about building smart digital solutions.</p>
      </div>
      <div class="team-card">
        <img src="Images/Chamath.jpg" alt="Mandira">
        <h3>D.A.A. Mandira Chamath</h3>
        <p>UI/UX Designer. Focused on creating user-friendly, accessible interfaces.</p>
      </div>
      <div class="team-card">
        <img src="Images/pavith.jpg" alt="Rajapaksha">
        <h3>S.P. Rajapaksha</h3>
        <p>Backend Developer. Specializes in secure and efficient server-side development.</p>
      </div>
      <div class="team-card">
        <img src="Images/Kenul.jpg" alt="Weerasinghe">
        <h3>E.M.K.H.W. Weerasinghe</h3>
        <p>Database Manager. Expert in reliable and scalable data systems.</p>
      </div>
      <div class="team-card">
        <img src="Images/sahanmi.jpg" alt="Hiranya">
        <h3>Hiranya Sahanmi</h3>
        <p>Quality Assurance. Ensures flawless user experience via testing and debugging.</p>
      </div>
    </div>
  </section>

  <section class="information">
    <p class="sub-title">GET TO KNOW US</p>
    <h1 class="main-title">Why We're Your Perfect Travel Partner</h1>
    <p class="description">
      We make your journey simple, safe, and memorable — with trust and local expertise.
    </p>
    <div class="features">
      <div class="feature-item slide-hidden">
        <div class="icon-circle"><i class="fa fa-comment-dots"></i></div>
        <h3>25 million +</h3>
        <p>Trusted by travelers worldwide with authentic experiences.</p>
      </div>
      <div class="feature-item slide-hidden">
        <div class="icon-circle"><i class="fa fa-smile"></i></div>
        <h3>No hidden fees</h3>
        <p>Transparent pricing and complete clarity in bookings.</p>
      </div>
      <div class="feature-item slide-hidden">
        <div class="icon-circle"><i class="fa fa-check-circle"></i></div>
        <h3>Booking flexibility</h3>
        <p>Modify or cancel trips with ease and peace of mind.</p>
      </div>
      <div class="feature-item slide-hidden">
        <div class="icon-circle"><i class="fa fa-bus"></i></div>
        <h3>Included transfers</h3>
        <p>Hassle-free transfers and travel assistance everywhere.</p>
      </div>
    </div>
  </section>

  <section class="guides-section">
    <h2>Our Tour Guides</h2>

    <div class="guides-wrapper">
      <div class="guides-rail" id="guidesRail" aria-label="Guide list">
        <?php if (!empty($guides)): ?>
          <?php foreach ($guides as $g): ?>
            <?php
              $name = trim(($g['F_Name'] ?? '') . ' ' . ($g['L_Name'] ?? ''));
              $desc = isset($g['Description']) ? (string)$g['Description'] : '';
              $phone = isset($g['Phone_No']) ? trim((string)$g['Phone_No']) : '';
              $rating = isset($g['Rating']) ? (float)$g['Rating'] : 0;
              $stars = str_repeat('⭐', max(0, min(5, (int)round($rating))));
              $langs = trim((string)($g['Languages'] ?? ''));
              $profile = isset($g['User_Profile']) && $g['User_Profile'] !== ''
                ? 'uploads/UserProfiles/' . basename($g['User_Profile'])
                : 'CJ.jpg';
            ?>
            <article class="guide-card" role="group" aria-roledescription="slide">
              <img src="<?php echo htmlspecialchars($profile); ?>" alt="<?php echo htmlspecialchars($name); ?>">
              <h3><?php echo htmlspecialchars($name); ?></h3>
              <?php if ($phone !== ''): ?>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($phone); ?></p>
              <?php endif; ?>
              <p><?php echo htmlspecialchars(mb_strimwidth($desc, 0, 140, '…', 'UTF-8')); ?></p>
              <?php if ($langs !== ''): ?>
                <p><strong>Languages:</strong> <?php echo htmlspecialchars($langs); ?></p>
              <?php endif; ?>
              <p class="rating"><?php echo $stars !== '' ? $stars : '⭐⭐⭐⭐⭐'; ?></p>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <article class="guide-card" role="group" aria-roledescription="slide">
            <img src="CJ.jpg" alt="No Guides">
            <h3>Coming Soon</h3>
            <p>New guides will appear here shortly.</p>
            <p class="rating">⭐⭐⭐⭐⭐</p>
          </article>
        <?php endif; ?>
      </div>
    </div>

    <div class="carousel-dots" id="guideDots" hidden></div>
  </section>

  <?php include __DIR__ . '/Includes/footer.php'; ?>

  <script>
    const animatedElements = document.querySelectorAll('.slide-hidden');
    const appearOnScroll = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('show');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.2 });
    animatedElements.forEach(el => appearOnScroll.observe(el));
  </script>

  <script>
    (function () {
      const rail = document.getElementById('guidesRail');
      if (!rail) return;

      const dotsEl = document.getElementById('guideDots');
      const GAP = 24;
      let cardWidth = 0;
      let timer = null;
      let index = 0;
      let cards = [];

      function refresh() {
        cards = [...rail.querySelectorAll('.guide-card')];
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
</body>
</html>
