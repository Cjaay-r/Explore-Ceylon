<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';

$sql = "
  SELECT 
    d.Destination_ID,
    d.Name,
    d.Description,
    d.District,
    d.latitude,
    d.longitude,
    i.Image_ID,
    i.Image_Url,
    i.AltText
  FROM destinations d
  LEFT JOIN destination_imgs i ON i.Destination_ID = d.Destination_ID
  ORDER BY d.Name ASC, i.Image_ID ASC
";
$result = $conn->query($sql);
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$destinations = [];
foreach ($rows as $r) {
  $id = (int)$r['Destination_ID'];
  if (!isset($destinations[$id])) {
    $destinations[$id] = [
      'id' => $id,
      'name' => $r['Name'],
      'desc' => $r['Description'],
      'district' => $r['District'],
      'lat' => $r['latitude'],
      'lon' => $r['longitude'],
      'images' => []
    ];
  }
  if (!empty($r['Image_Url']) && count($destinations[$id]['images']) < 3) {
    $destinations[$id]['images'][] = [
      'url' => $r['Image_Url'],
      'alt' => $r['AltText'] ?: $r['Name']
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sri Lankan Destinations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link rel="stylesheet" href="Styles/destinations.css">
</head>
<body>

<?php include __DIR__ . '/Includes/header.php'; ?>

<!-- Hero Section -->
<section class="topic" data-aos="fade-zoom-in" data-aos-duration="1200" data-aos-easing="ease-in-out">
  <div class="topic-overlay"></div>
  <div class="text">
    <h1 data-aos="fade-down" data-aos-delay="200">Discover Sri Lanka</h1>
    <p data-aos="fade-up" data-aos-delay="400">Explore the beauty, culture, and heritage across the island</p>
  </div>
</section>

<!-- Destinations Grid -->
<section class="container my-5" data-aos="fade-up" data-aos-duration="1000">
  <h2 class="mb-4 text-center fw-bold text-primary" data-aos="fade-up" data-aos-delay="200">Explore Sri Lanka</h2>

  <div class="row g-4">
    <?php if (!$destinations): ?>
      <div class="col-12" data-aos="fade-up">
        <div class="alert alert-info text-center">No destinations available yet.</div>
      </div>
    <?php else: ?>
      <?php foreach ($destinations as $d): 
        $cid = 'carousel-d' . $d['id'];
        $imgs = $d['images'];
        if (count($imgs) === 1)      $imgs = array_merge($imgs, [$imgs[0], $imgs[0]]);
        elseif (count($imgs) === 2) $imgs[] = $imgs[0];
      ?>
      <div class="col-lg-4 col-md-6 col-sm-12" data-aos="zoom-in" data-aos-delay="200">
        <div class="card destination-card h-100 border-0 rounded-4 overflow-hidden shadow-sm">
          <div id="<?php echo $cid; ?>" class="carousel slide carousel-fade" data-bs-ride="carousel">
            <div class="carousel-inner">
              <?php foreach ($imgs as $idx => $img): ?>
                <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>">
                  <img src="<?php echo htmlspecialchars($img['url']); ?>" 
                       class="d-block w-100 destination-img" 
                       alt="<?php echo htmlspecialchars($img['alt']); ?>">
                </div>
              <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $cid; ?>" data-bs-slide="prev" aria-label="Previous slide">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $cid; ?>" data-bs-slide="next" aria-label="Next slide">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
          </div>

          <div class="card-body p-3 p-md-4" data-aos="fade-up" data-aos-delay="300">
            <h5 class="destination-title mb-1 text-truncate"><?php echo htmlspecialchars($d['name']); ?></h5>
            <div class="small text-secondary mb-2"><?php echo htmlspecialchars($d['district']); ?></div>
            <div class="weather small text-muted mb-2"
                 data-weather
                 data-lat="<?php echo htmlspecialchars($d['lat']); ?>"
                 data-lon="<?php echo htmlspecialchars($d['lon']); ?>"
                 aria-live="polite">Loading weather…</div>
            <p class="card-text text-muted"><?php echo nl2br(htmlspecialchars($d['desc'])); ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/Includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({
  once: true,
  duration: 1000,
  easing: 'ease-in-out',
});

(function () {
  const codeText = (c) => {
    const m = {
      0:"Clear",1:"Mainly clear",2:"Partly cloudy",3:"Overcast",
      45:"Fog",48:"Rime fog",
      51:"Light drizzle",53:"Drizzle",55:"Heavy drizzle",
      56:"Light freezing drizzle",57:"Heavy freezing drizzle",
      61:"Light rain",63:"Rain",65:"Heavy rain",
      66:"Light freezing rain",67:"Heavy freezing rain",
      71:"Light snowfall",73:"Snowfall",75:"Heavy snowfall",
      77:"Snow grains",
      80:"Light rain showers",81:"Rain showers",82:"Violent rain showers",
      85:"Light snow showers",86:"Heavy snow showers",
      95:"Thunderstorm",96:"Thunderstorm (slight hail)",99:"Thunderstorm (heavy hail)"
    };
    return m[c] ?? "—";
  };

  const fetchWeather = async (lat, lon) => {
    const url = new URL("https://api.open-meteo.com/v1/forecast");
    url.searchParams.set("latitude", lat);
    url.searchParams.set("longitude", lon);
    url.searchParams.set("current", "temperature_2m,apparent_temperature,weather_code");
    url.searchParams.set("timezone", "auto");
    const res = await fetch(url.toString(), { cache: "no-store" });
    if (!res.ok) throw new Error("Weather fetch failed");
    return res.json();
  };

  const render = (el, data) => {
    const cur = data.current || {};
    const t = cur.temperature_2m;
    const feels = cur.apparent_temperature;
    const code = cur.weather_code;
    el.classList.remove("text-muted");
    el.textContent = (t != null)
      ? `${Math.round(t)}°C · ${codeText(code)}${(feels!=null?` · feels ${Math.round(feels)}°C`:"")}`
      : "Weather unavailable";
  };

  const onVisible = async (el) => {
    const lat = el.getAttribute("data-lat");
    const lon = el.getAttribute("data-lon");
    if (!lat || !lon) { el.textContent = "No coordinates"; return; }
    try { render(el, await fetchWeather(lat, lon)); }
    catch { el.textContent = "Weather unavailable"; }
  };

  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        obs.unobserve(e.target);
        onVisible(e.target);
      }
    });
  }, { rootMargin: "200px" });

  document.querySelectorAll("[data-weather]").forEach(el => io.observe(el));
})();
</script>
</body>
</html>
