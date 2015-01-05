Dear <?php echo $captains; ?>,

Your invitation for <?php echo $person['full_name']; ?> to join the roster of the <?php
echo Configure::read('organization.name'); ?> team <?php echo $team['name']; ?> has been declined.

The <?php echo $team['name']; ?> roster may be accessed at
<?php echo Router::url(array('controller' => 'teams', 'action' => 'view', 'team' => $team['id']), true); ?>

You need to be logged into the website to update this.

<?php echo $this->element('email/text/footer'); ?>
