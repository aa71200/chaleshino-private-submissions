<?php
/** Render the role fixture decisions. */

namespace Chaleshino\CallReview;

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Private submissions · Chaleshino</title><link rel="stylesheet" href="preview.css">
</head><body><main>
<header><p>Chaleshino Call Review</p><h1>Private submissions</h1><p>Who can download a submitted file?</p></header>
<section aria-labelledby="sample-entry"><div class="file">
<div><h2 id="sample-entry">Community workshop poster</h2><p>Demo entry · PDF</p></div><span class="protected">Private file</span>
</div><table><thead><tr><th>Role</th><th>Download</th></tr></thead><tbody>
<?php foreach ( $results as [ $role, $reason, $allowed ] ) : ?>
<tr><th scope="row"><?php echo htmlspecialchars( $role ); ?><small><?php echo htmlspecialchars( $reason ); ?></small></th>
<td><span class="<?php echo $allowed ? 'allowed' : 'denied'; ?>"><?php echo $allowed ? 'Allowed' : 'Denied'; ?></span></td></tr>
<?php endforeach; ?>
</tbody></table></section>
</main></body></html>
