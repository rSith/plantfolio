<?php
// About page: what PlantFolio is, the community guidelines and how to reach the team.
// An extra public page (not in the wireframes) that the footer and the sign-up form link to. No task ID yet.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// The sections of this page; the footer links jump straight to them (about.php#guidelines).
$sections = [
    'about'      => 'About',
    'guidelines' => 'Community guidelines',
    'contact'    => 'Contact',
];

// The rules a member agrees to at sign-up. A first draft: the team edits the wording before release.
$guidelines = [
    ['title' => 'Be kind',
     'text'  => 'Write comments and messages the way you would talk to a neighbour over the garden fence. No insults, harassment or spam.'],
    ['title' => 'Describe plants honestly',
     'text'  => 'Say what the plant really is and how healthy it is, including any pests or disease. Use your own photos.'],
    ['title' => 'Swap or give, never sell',
     'text'  => 'The exchange is for swaps and free plants between members. Listings that ask for money are removed.'],
    ['title' => 'Meet safely',
     'text'  => 'Hand plants over in a public place in daylight, and tell someone where you are going.'],
    ['title' => 'Respect privacy',
     'text'  => 'Contact details are shown to you for one exchange. Do not pass them on or use them for anything else.'],
    ['title' => 'Treat AI results as suggestions',
     'text'  => 'The Plant Identifier and the Health Check can be wrong. Check a result before you act on it.'],
];

// SAMPLE contact details. The team's real ones are added at final delivery (stage D).
$contact = [
    'email'      => 'hello@plantfolio.example',
    'reply_time' => 'We usually reply within two working days.',
];

$page_title = 'About';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="prose">
    <h1>About PlantFolio</h1>
    <p>Where your plants tell their story.</p>

    <nav class="prose-nav" aria-label="On this page">
      <?php foreach ($sections as $id => $label): ?>
        <a class="chip" href="#<?= e($id) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <section id="about" aria-labelledby="about-title">
      <h2 id="about-title">What PlantFolio is</h2>
      <p>PlantFolio is a community for people who love plants. Every plant gets its own profile with photos,
        a health story and care reminders, so you always know what it needs today.</p>
      <p>Members swap cuttings and spare plants with people nearby, and two smart tools help along the way:
        one suggests the species from a photo, the other looks for signs of disease on a leaf.</p>
      <p>The site is a group project by Computer Science students at Wayamba University of Sri Lanka.</p>
    </section>

    <section id="guidelines" aria-labelledby="guidelines-title">
      <h2 id="guidelines-title">Community guidelines</h2>
      <p>Everyone agrees to these when they join. They keep PlantFolio friendly and the exchange fair.</p>
      <ol class="rule-list">
        <?php foreach ($guidelines as $rule): ?>
          <li>
            <h3><?= e($rule['title']) ?></h3>
            <p><?= e($rule['text']) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <section id="contact" aria-labelledby="contact-title">
      <h2 id="contact-title">Contact</h2>
      <p>Questions, a problem with a listing, or a member who is not following the guidelines? Write to us at
        <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>.</p>
      <p class="text-small"><?= e($contact['reply_time']) ?></p>
      <div class="alert"><?= icon('info') ?> These are sample contact details. The real ones are added before the final release.</div>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
