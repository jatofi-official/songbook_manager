<?php
session_start();
$title = "O projekte";
$phpBase = 'php/';
include $phpBase . 'db.php';
include $phpBase . 'data.php';
include $phpBase . 'functions.php';
include $phpBase . 'header.php';
include $phpBase . 'navigation.php';
include $phpBase . 'auth.php';


?>

<!-- Main -->
<main class="about-container">
  <section class="about-card">
    <h1>O projekte</h1>
    <p>
      <strong>spevnik.mustangy.sk</strong> je projekt určený pre hudobníkov, ale aj celé komunity. Má "škatuľkový" prístup: jednotlivé komunity môžu nerušene vytvárať vlastné spevníky, iba pre členov komunity.
    </p>

    <h2>Cieľ projektu</h2>
    <p>
      Cieľom tohoto projektu je:</p>
      <ul> 
        <li>vytvoriť centrálnu hudobné databázu, kde si každý vie pridať pesničky, ktoré ho zaujímajú alebo hrať už existujúce pesničky.</li>
        <li>vytvoriť jednoduchý, rýchly a prístupný spôsob, ako vytvárať vlastné spevníky rovno na tlač</li>
      </ul>
      <p>a to všetko <strong>bezplatne</strong>.

    <h2>Funkcie</h2>
    <ul>
      <li>Usporiadanie a filtrovanie piesní podľa kategórií, tagov a nástrojov</li>
      <li>Verejné a súkromné pesničky</li>
      <li>Zobrazenie akordov</li>
      <li>Jednoduché vytváranie spevníkov</li>
      <li>Zbieranie <strong>xp</strong> za kontribúciu</li>
      
    </ul>
  <a href="/plan/" class="roadmap-link">
    📝 Roadmapa – čo plánujeme pridať ďalej
  </a>
    <h2>Komunita</h2>
    <p>
      Projekt vznikol pre 24. skautský zbor <strong>Mustangy</strong>,
      no otvorený je každému, kto má rád hudbu a hľadá jednoduchý, pekný spôsob správy spevníka.
    </p>
    <p>Všetky texty piesní sú určené na osobné použitie a v prípade nesúhlasu autora ich na požiadanie stiahneme. Pridávanie piesní je na vlastnú zodpovednosť.</p>

    <h2>Kontakt</h2>
    <p>
      Máte otázky, návrhy alebo dotazy?
      Napíšte správcovi spevníku jakubo.hlavaty@gmail.com.
    </p>
  </section>
</main>
<style>
.roadmap-link {
    display: inline-block;
    padding: 8px 14px;
    background-color: #f0f4f8; /* jemný svetlý box */
    border: 1px solid #ddd;
    border-radius: 8px;
    text-decoration: none;
    color: #333; /* tmavá neutrálná farba */
    font-weight: 500;
    transition: background 0.2s, transform 0.2s;
}

.roadmap-link:hover {
    background-color: #e2e8f0; /* jemné ztmavenie pri hover */
    transform: translateY(-2px);
    color: #0077cc; /* jemné zvýraznenie textu pri hover */
}
</style>

<style>
  .about-container {
    display: flex;
    justify-content: center;
    padding: 40px 20px;
  }
  .about-card {
    max-width: 800px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 12px;
    padding: 30px 40px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  }
  .about-card h1 {
    margin-top: 0;
    font-size: 2rem;
    color: #333;
  }
  .about-card h2 {
    margin-top: 1.5em;
    font-size: 1.3rem;
    color: #444;
  }
  .about-card p {
    color: #555;
    line-height: 1.6;
  }
  .about-card ul {
    margin: 0.5em 0 0 1.2em;
    color: #555;
    padding: 0;
  }
  .about-card li {
    margin: 0.3em 0;
  }
  .highlight {
    color: #0077cc;
  }
</style>

<?php
include $phpBase . 'footer.php';
?>
