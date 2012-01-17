<p>Dear <?php echo $captains; ?>,</p>
<p><?php echo $person['full_name']; ?> has <?php
if (empty($comment)):
?>removed the comment from <?php
else:
?>added the following comment to <?php
endif;
?>their attendance at the <?php echo $team['name']; ?> event "<?php echo $event['TeamEvent']['name'];
?>" at <?php echo $event['TeamEvent']['location_name'];
$address = "{$event['TeamEvent']['location_street']}, {$event['TeamEvent']['location_city']}, {$event['TeamEvent']['location_province']}";
$link_address = strtr ($address, ' ', '+');
echo ' (' . $this->Html->link($address, "http://maps.google.com/maps?q=$link_address") . ')';
?> starting at <?php echo $this->ZuluruTime->time($event['TeamEvent']['start']);
?> on <?php
echo $this->ZuluruTime->date($event['TeamEvent']['date']);
?>.</p>
<?php if (!empty($comment)): ?>
<p><?php echo $comment; ?></p>
<?php endif; ?>
<p>Thanks,
<br /><?php echo Configure::read('email.admin_name'); ?>
<br /><?php echo Configure::read('organization.short_name'); ?> web team</p>