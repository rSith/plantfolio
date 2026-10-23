<?php
// Analysing a photo (state S2, wireframe p24): shown while the Flask service runs the model (ML-17, UI half).
// Expects $title — what is happening, e.g. "Analysing leaves…" — and $cancel_url, the page to go back to.
// role="status" makes a screen reader announce the title. The moving bar only shows that something is happening:
// stage F shows this state with ?analysing; stage M shows it while PHP waits for Flask (10 seconds at most), and after
// that time the page offers to try again or to go on by hand, so a slow service never leaves the member stuck.
?>
<section class="form-card analysing" aria-labelledby="analysing-title" aria-busy="true">
  <h2 class="analysing-title" id="analysing-title" role="status"><span class="spinner" aria-hidden="true"></span> <?= e($title) ?></h2>
  <p class="analysing-text">This usually takes a few seconds.</p>
  <div class="analysing-bar" aria-hidden="true"><span></span></div>
  <a class="btn btn-ghost btn-sm" href="<?= e($cancel_url) ?>">Cancel</a>
</section>
