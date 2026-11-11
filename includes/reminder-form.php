<?php
// Reminders (CORE-22, CORE-23, UI half): Add a reminder (choices, server-side checks, saving), Mark done, and the
// toast with Undo.
// Shared by the Dashboard (any of the member's plants) and the Plant Profile (that one plant).
// Moved here from dashboard.php on 2026-11-11 so the three pages use one copy. The popup itself is
// partials/reminder-dialog.php, the toast partials/reminder-toast.php.
// Stage F keeps the reminder in the session (mock_add_reminder()), so it shows on every page at once.
// Stage B: INSERT INTO care_reminders … ON DUPLICATE KEY UPDATE (one reminder per plant and task), after a CSRF check.

/** The seven days of the "On chosen days" repeat, as the form sends them (mon … sun). */
function reminder_weekdays()
{
    return ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
}

/** How a reminder can repeat: the repeat_type values and their words. */
function reminder_repeat_options()
{
    return [
        'once'     => 'Does not repeat',
        'daily'    => 'Every day',
        'days'     => 'On chosen days',
        'interval' => 'Every few days',
    ];
}

/** The popup's starting values. $plant_id fills in the plant when the page is about one plant. */
function reminder_defaults($plant_id = '')
{
    return ['plant' => (string) $plant_id, 'task' => 'water', 'time' => '08:00', 'start' => date('Y-m-d'),
            'repeat' => 'interval', 'days' => [], 'interval' => '7'];
}

/**
 * The first day a new reminder is due: its start day, or for "On chosen days" the first chosen weekday from then.
 * date('D') gives "Mon", which lowercased matches the day values of the form.
 */
function first_due($start, $repeat, $days)
{
    if ($repeat !== 'days') {
        return $start;
    }
    for ($offset = 0; $offset < 7; $offset++) {
        $day = date('Y-m-d', strtotime($start . ' +' . $offset . ' days'));
        if (in_array(strtolower(date('D', strtotime($day))), $days, true)) {
            return $day;
        }
    }
    return $start;
}

/**
 * The next due day after a task was done on $done_day (Mark done): the next chosen weekday for "On chosen days",
 * otherwise $done_day plus interval_days (1 for every day), the rule next_due = last_done + interval_days.
 */
function next_due_after($reminder, $done_day)
{
    if (($reminder['repeat_type'] ?? 'interval') === 'days') {
        return first_due(date('Y-m-d', strtotime($done_day . ' +1 day')), 'days', $reminder['repeat_days']);
    }
    return date('Y-m-d', strtotime($done_day . ' +' . (int) $reminder['interval_days'] . ' days'));
}

/**
 * Mark done (CORE-23): last_done becomes today and next_due moves on by the reminder's own rule; a one-off reminder
 * is finished, so it goes. The Story gets a care line ("Watered · 11 Nov"). The toast with Undo waits in the session.
 * $key is "plant_id-task" from the form; $rows are the reminders that may be marked on this page (joined with the
 * plant's nickname), so a made-up key changes nothing. Moved here from dashboard.php on 2026-11-11.
 * Stage B: UPDATE care_reminders SET last_done = CURDATE(), next_due = … WHERE id = ? (owner checked through plants),
 *          or DELETE for a one-off reminder; INSERT INTO health_logs (type 'care', care_task) in the same transaction.
 */
function reminder_mark_done($key, array $rows)
{
    $row = null;
    foreach ($rows as $candidate) {
        if ($candidate['plant_id'] . '-' . $candidate['task'] === $key) {
            $row = $candidate;
        }
    }
    if (!$row) {
        return;
    }

    $today    = date('Y-m-d');
    $reminder = array_diff_key($row, array_flip(['nickname', 'photo', 'common_name']));   // the care_reminders columns only
    $done     = task_done_label($row['task']) . ' ' . $row['nickname'];

    if (($reminder['repeat_type'] ?? 'interval') === 'once') {
        $updated = ['plant_id' => $reminder['plant_id'], 'task' => $reminder['task'], 'deleted' => true];
        $message = $done . ' · reminder finished';
    } else {
        $updated = array_merge($reminder, ['last_done' => $today, 'next_due' => next_due_after($reminder, $today)]);
        $message = $done . ' · next on ' . format_day($updated['next_due']);
    }

    // A re-check is not care given to the plant, so it adds nothing to the Story
    $log_id = null;
    if ($row['task'] !== 'recheck') {
        $log_id = mock_add_health_log([
            'plant_id' => $row['plant_id'], 'type' => 'care', 'care_task' => $row['task'], 'status' => null,
            'source' => 'manual', 'photo' => null, 'note' => '', 'logged_at' => date('Y-m-d H:i:s'),
            'prediction_type' => null, 'predicted_label' => null, 'confidence' => null,
        ]);
    }

    $_SESSION['reminder_toast'] = [
        'message'  => $message,
        'key'      => $key,
        'previous' => mock_add_reminder($updated),
        'log'      => $log_id,
        'undone'   => task_label($row['task']) . ' ' . $row['nickname'] . ' is back on the list.',
        'icon'     => 'check',
    ];
}

/** aria-invalid and aria-describedby for a field with an error, so a screen reader reads the message with it. */
function reminder_error_attrs($field, $errors)
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="reminder-' . $field . '-error"' : '';
}

/**
 * Reads and checks a posted Add a reminder form. $plants_by_id holds the plants the member may choose: all of
 * theirs on the Dashboard, just this one on a plant's pages (the ownership check). Returns [$values, $errors].
 * With no errors the reminder is saved and the toast is waiting in the session, so the page only redirects.
 */
function reminder_form_save($plants_by_id)
{
    $values = reminder_defaults();
    $errors = [];   // field name => message, printed under that field in the popup
    $today  = date('Y-m-d');

    foreach (['plant', 'task', 'time', 'start', 'repeat', 'interval'] as $name) {
        $values[$name] = post_text($name);
    }
    // The day boxes arrive as a list (days[]=mon&days[]=wed): keep only text that is a known day
    $posted_days    = $_POST['days'] ?? [];
    $posted_days    = is_array($posted_days) ? array_filter($posted_days, 'is_string') : [];
    $values['days'] = array_values(array_intersect(array_keys(reminder_weekdays()), $posted_days));

    // The same rules the browser checks, checked again here: the browser's checks can be switched off
    $plant_id = $values['plant'];
    if (!ctype_digit($plant_id) || !isset($plants_by_id[(int) $plant_id])) {
        $errors['plant'] = 'Choose one of your plants.';
    }
    if (!in_array($values['task'], reminder_tasks(), true)) {
        $errors['task'] = 'Choose a care task.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $values['time'])) {
        $errors['time'] = 'Choose a time.';
    }
    // '!' starts the date at midnight; comparing it back as text rejects dates like 2026-02-31
    $start = DateTime::createFromFormat('!Y-m-d', $values['start']);
    if (!$start || $start->format('Y-m-d') !== $values['start'] || $values['start'] < $today) {
        $errors['start'] = 'Choose today or a later day.';
    }
    if (!array_key_exists($values['repeat'], reminder_repeat_options())) {
        $errors['repeat'] = 'Choose how often it repeats.';
    } elseif ($values['repeat'] === 'days' && !$values['days']) {
        $errors['days'] = 'Choose at least one day.';
    } elseif ($values['repeat'] === 'interval'
        && (!ctype_digit($values['interval']) || $values['interval'] < 2 || $values['interval'] > 365)) {
        $errors['interval'] = 'Use a number from 2 to 365.';
    }

    if ($errors) {
        return [$values, $errors];
    }

    $repeat   = $values['repeat'];
    $reminder = [
        'id'            => 900 + count($_SESSION[preview_key('reminders')] ?? []),   // made up: stage B's AUTO_INCREMENT
        'plant_id'      => (int) $plant_id,
        'task'          => $values['task'],
        'interval_days' => $repeat === 'daily' ? 1 : ($repeat === 'interval' ? (int) $values['interval'] : null),
        'last_done'     => null,
        'next_due'      => first_due($values['start'], $repeat, $values['days']),
        'remind_time'   => $values['time'],
        'repeat_type'   => $repeat,
        'repeat_days'   => $repeat === 'days' ? $values['days'] : [],
    ];
    $previous = mock_add_reminder($reminder);

    // The toast on the next page; Undo needs the key and the row this one replaced, kept here, not in the form
    $plant = $plants_by_id[$reminder['plant_id']];
    $_SESSION['reminder_toast'] = [
        'message'  => 'Reminder added: ' . task_label($reminder['task']) . ' ' . $plant['nickname'] . ' · ' . schedule_words($reminder),
        'key'      => $reminder['plant_id'] . '-' . $reminder['task'],
        'previous' => $previous,
        'undone'   => 'Reminder cancelled.',
    ];

    return [$values, []];
}

/**
 * Undo in the toast: takes back the last Add reminder or Mark done. Only the session knows which reminder that was,
 * so nothing in the form can point it at another member's reminder.
 */
function reminder_undo()
{
    $undo = $_SESSION['reminder_undo'] ?? null;
    unset($_SESSION['reminder_undo']);
    if ($undo) {
        mock_undo_reminder($undo['key'], $undo['previous']);
        if (!empty($undo['log'])) {
            mock_remove_health_log($undo['log']);   // the Story's care line of a Mark done goes too
        }
        $_SESSION['reminder_toast'] = ['message' => $undo['undone'], 'key' => null, 'previous' => null, 'undone' => null];
    }
}

/**
 * The toast shows once: it is taken out of the session as soon as it is read. Its Undo stays possible until the
 * next Add reminder or Mark done. Returns the toast, or null.
 */
function reminder_toast_take()
{
    $toast = $_SESSION['reminder_toast'] ?? null;
    unset($_SESSION['reminder_toast']);
    if ($toast && $toast['key']) {
        $_SESSION['reminder_undo'] = ['key' => $toast['key'], 'previous' => $toast['previous'], 'undone' => $toast['undone'],
                                      'log' => $toast['log'] ?? null];
    }
    return $toast;
}
