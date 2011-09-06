<?php
class RegistrationsController extends AppController {

	var $name = 'Registrations';
	var $components = array('Questionnaire', 'CanRegister');
	var $paginate = array(
		'Registration' => array(
			'contain' => array('Person'),
			'order' => array('Registration.payment' => 'DESC', 'Registration.created' => 'DESC'),
		),
	);

	function isAuthorized() {
		// Anyone that's logged in can perform these operations
		if (in_array ($this->params['action'], array(
				'register',
				'unregister',
				'checkout',
		)))
		{
			return true;
		}

		return false;
	}

	function full_list() {
		$id = $this->_arg('event');
		if (!$id) {
			$this->Session->setFlash(__('Invalid event', true));
			$this->redirect('/');
		}

		$this->Registration->Event->contain (array(
			'EventType',
			'Questionnaire' => array('Question' => array('Answer')),
		));
		$event = $this->Registration->Event->read(null, $id);
		if ($event === false) {
			$this->Session->setFlash(__('Invalid Event', true));
			$this->redirect(array('controller' => 'events', 'action' => 'index'));
		}

		$event_obj = $this->_getComponent ('EventType', $event['EventType']['type'], $this);
		$this->_mergeAutoQuestions ($event, $event_obj, $event['Questionnaire'], null, true);

		if ($this->params['url']['ext'] == 'csv') {
			$this->Registration->contain ('Person', 'Response');
			$this->set('registrations', $this->Registration->find ('all', array(
					'conditions' => array('Registration.event_id' => $id),
					'order' => array('Registration.payment' => 'DESC', 'Registration.created' => 'DESC'),
			)));
			$this->set('download_file_name', "Registrations - {$event['Event']['name']}");
			Configure::write ('debug', 0);
		} else {
			$this->set('registrations', $this->paginate ('Registration', array('event_id' => $id)));
		}
		$this->set(compact('event'));
	}

	function summary() {
		$id = $this->_arg('event');
		if (!$id) {
			$this->Session->setFlash(__('Invalid event', true));
			$this->redirect(array('controller' => 'events', 'action' => 'index'));
		}

		$this->Registration->Event->contain (array(
			'EventType',
			'Questionnaire' => array('Question' => array('Answer')),
		));
		$event = $this->Registration->Event->read(null, $id);
		if ($event === false) {
			$this->Session->setFlash(__('Invalid Event', true));
			$this->redirect(array('controller' => 'events', 'action' => 'index'));
		}

		$event_obj = $this->_getComponent ('EventType', $event['EventType']['type'], $this);
		$this->_mergeAutoQuestions ($event, $event_obj, $event['Questionnaire'], null, true);

		$this->Registration->contain ('Person');
		$gender = $this->Registration->find('all', array(
				'fields' => array(
					'Person.gender',
					'COUNT(Registration.id) AS count',
				),
				'conditions' => array(
					'Registration.event_id' => $id,
					'Registration.payment !=' => 'Refunded',
				),
				'group' => 'Person.gender',
				'order' => array ('Person.gender' => 'DESC'),
		));

		$this->Registration->contain ();
		$payment = $this->Registration->find('all', array(
				'fields' => array(
					'payment',
					'COUNT(payment) AS count',
				),
				'conditions' => array(
					'event_id' => $id,
				),
				'group' => 'payment',
				'order' => 'payment',
		));

		$this->Registration->Response->contain ();
		$responses = $this->Registration->Response->find('all', array(
				'fields' => array(
					'question_id',
					'answer_id',
					'COUNT(answer_id) AS count',
				),
				'conditions' => array(
					'event_id' => $id,
					'answer' => null,
				),
				'group' => array('question_id', 'answer_id'),
				'order' => 'question_id',
		));

		$this->set(compact ('event', 'gender', 'payment', 'responses'));
	}

	function statistics() {
		$year = $this->_arg('year');
		if ($year === null) {
			$year = date('Y');
		}

		$this->Registration->contain ();
		$this->set('events', $this->Registration->find('all', array(
			'fields' => array(
				'Event.id',
				'Event.name',
				'EventType.name',
				'COUNT(Registration.id) AS count',
			),
			'conditions' => array(
				'Registration.payment !=' => 'Refunded',
				'OR' => array(
					'YEAR(Event.open)' => $year,
					'YEAR(Event.close)' => $year,
				),
			),
			'group' => 'Event.id',
			'order' => array('Event.event_type_id', 'Event.open' => 'DESC', 'Event.close' => 'DESC', 'Event.id'),
			'joins' => array(
				array(
					'table' => "{$this->Registration->tablePrefix}events",
					'alias' => 'Event',
					'type' => 'LEFT',
					'foreignKey' => false,
					'conditions' => array('Registration.event_id = Event.id'),
				),
				array(
					'table' => "{$this->Registration->tablePrefix}event_types",
					'alias' => 'EventType',
					'type' => 'LEFT',
					'foreignKey' => false,
					'conditions' => array('Event.event_type_id = EventType.id'),
				),
			),
		)));

		$this->Registration->Event->contain();
		$this->set('years', $this->Registration->Event->find('all', array(
			'fields' => 'DISTINCT YEAR(open) AS year',
			'order' => 'open',
		)));
	}

	function report() {
		$start_date = '2011-01-01';
		$end_date = '2011-12-31';
		$conditions = array(
			'Registration.created >=' => $start_date,
			'Registration.created <=' => $end_date,
		);
		$contain = array(
			'Event' => 'EventType',
			'Person',
		);
		$order = array('Registration.payment' => 'DESC', 'Registration.created');

		if ($this->params['url']['ext'] == 'csv') {
			$this->set('registrations', $this->Registration->find ('all', compact('conditions', 'contain', 'order')));
			$this->set('download_file_name', 'Registrations');
		} else {
			$this->paginate = array(
				'Registration' => compact('conditions', 'contain', 'order'),
			);
			$this->set('registrations', $this->paginate ('Registration'));
		}
	}

	function view() {
		$id = $this->_arg('registration');
		if (!$id) {
			$this->Session->setFlash(__('Invalid registration', true));
			$this->redirect(array('controller' => 'events', 'action' => 'index'));
		}
		$this->Registration->contain (array(
			'Person',
			'Event' => array(
				'EventType',
				'Questionnaire' => array('Question' => array('Answer')),
			),
			'Response',
			'RegistrationAudit',
		));
		$registration = $this->Registration->read(null, $id);
		if (!$registration) {
			$this->Session->setFlash(__('Invalid registration', true));
			$this->redirect(array('controller' => 'events', 'action' => 'index'));
		}

		$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
		$this->_mergeAutoQuestions ($registration, $event_obj, $registration['Event']['Questionnaire'], $registration['Person']['id'], true);
		$this->set(compact('registration'));
	}

	function register() {
		$id = $this->_arg('event');
		if (!$id) {
			$this->Session->setFlash(__('Invalid event', true));
			$this->redirect(array('controller' => 'events', 'action' => 'wizard'));
		}

		$this->Registration->Event->contain (array(
			'EventType',
			'Questionnaire' => array(
				'Question' => array(
					'Answer' => array(
						'conditions' => array('active' => true),
					),
					'conditions' => array('active' => true),
				),
			),
		));
		$event = $this->Registration->Event->read(null, $id);
		if ($event === false) {
			$this->Session->setFlash(__('Invalid Event', true));
			$this->redirect(array('controller' => 'events', 'action' => 'wizard'));
		}

		// Re-do "can register" checks to make sure someone hasn't hand-fed us a URL
		$test = $this->CanRegister->test ($this->Auth->user('id'), $event);
		if (!$test['allowed']) {
			foreach ($test['messages'] as $key => $message) {
				if (is_array ($message)) {
					$test['messages'][$key] = $message['text'];
				}
			}
			$this->Session->setFlash(implode ('<br>', $test['messages']));
			$this->redirect(array('controller' => 'events', 'action' => 'wizard'));
		}

		// Check the waiver, if any
		$this->set('waivered', $this->_checkWaiver($event['Event']));

		$event_obj = $this->_getComponent ('EventType', $event['EventType']['type'], $this);
		$this->_mergeAutoQuestions ($event, $event_obj, $event['Questionnaire'], $this->Auth->user('id'));
		$this->set(compact ('id', 'event', 'event_obj'));

		// Wrap the whole thing in a transaction, for safety.
		$transaction = new DatabaseTransaction($this->Registration);

		// Data was posted, save it and proceed
		if (!empty($this->data)) {
			$this->Registration->Response->validate = array_merge(
				$this->Questionnaire->validation($event['Questionnaire']),
				$event_obj->registrationFieldsValidation ($event)
			);

			// Remove any unchecked checkboxes; we only save the checked ones.
			// $delete will be empty here, we don't need to do anything with it.
			list ($data, $delete) = $this->_splitResponses ($this->data);

			// This is all a little fragile, because of the weird format of the data we're saving.
			// We need to first set the response data, then validate it.  We can't rely on
			// Registration->saveAll to validate properly.
			$this->Registration->Response->set ($data);

			if (!$this->Registration->Response->validates()) {
				$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
				return;
			}

			// Use array_values here to get numeric keys in the data to be saved
			if (is_array($data) && array_key_exists('Response', $data)) {
				$data['Response'] = array_values($data['Response']);
			}

			// Set the flash message that will be used, if there are no errors
			$this->Session->setFlash(__('Your preferences for this registration have been saved.', true));
			$save = true;
		}

		if (empty ($event['Questionnaire']['Question'])) {
			// The event has no questionnaire, save trivial registration data and proceed
			$data = array('Registration' => array(), 'Response' => array());

			// Set the flash message that will be used, if there are no errors
			$this->Session->setFlash(__('Your registration for this event has been confirmed.', true));
			$save = true;
		}

		if (isset ($save)) {
			$data['Registration']['event_id'] = $id;

			// Next, we do the event registration
			$result = $event_obj->register($event, $data);
			if ($result === false) {
				$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
				return;
			}
			if (is_array ($result)) {
				$data['Response'] = array_merge($data['Response'], $result);
			}

			// Free events may need even more processing
			if ($event['Event']['cost'] == 0) {
				$result = $event_obj->paid($event, $data);
				if ($result === false) {
					$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
					return;
				}
				if (is_array ($result)) {
					$data['Response'] = array_merge($data['Response'], $result);
				}
				$data['Registration']['payment'] = 'Paid';
			}

			if (empty ($data['Response'])) {
				unset ($data['Response']);
			} else {
				// Manually add the event id to all of the responses :-(
				foreach (array_keys ($data['Response']) as $key) {
					$data['Response'][$key]['event_id'] = $id;
				}
			}

			if (!$this->Registration->saveAll($data, array('validate' => false))) {
				$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
				return;
			}

			$anonymous = Set::extract ('/Question[anonymous=1]/id', $event['Questionnaire']);
			if (!empty ($anonymous)) {
				$this->Registration->Response->updateAll (array('registration_id' => null),
					array('question_id' => $anonymous));
			}
			if ($transaction->commit() !== false) {
				$this->Session->delete ('Zuluru.Unpaid');
				$this->redirect(array('action' => 'checkout'));
			} else {
				$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
			}
		}
	}

	function checkout($op = null) {
		$this->Registration->contain (array(
			'Event' => array('EventType'),
			'Response',
		));
		$registrations = $this->Registration->find('all', array(
				'conditions' => array(
					'person_id' => $this->Auth->user('id'),
					'payment' => array('Unpaid', 'Pending'),
				),
		));

		// If there are no unpaid registrations, then we must have gotten here by
		// someone registering for a free event.  In that case, we don't want to
		// disturb the flash message, just go back to the event list.
		if (empty ($registrations)) {
			$this->Session->delete ('Zuluru.Unpaid');
			$this->redirect(array('controller' => 'events', 'action' => 'wizard'));
		}

		$this->Registration->Person->recursive = -1;
		$person = $this->Registration->Person->read (null, $this->Auth->user('id'));

		$full = array();
		foreach ($registrations as $key => $registration) {
			// Find the registration cap and how many are already registered.
			$conditions = array(
				'event_id' => $registration['Event']['id'],
				'payment' => array('Paid', 'Pending'),
			);
			if ($registration['Event']['cap_female'] != -2) {
				$conditions['gender'] = $person['Person']['gender'];
			}
			$cap = $this->Registration->Event->cap($registration['Event']['cap_male'], $registration['Event']['cap_female'], $person['Person']['gender']);
			if ($cap != -1) {
				$paid = $this->Registration->find ('count', array('conditions' => $conditions));
				if ($cap <= $paid) {
					$full[] = $registration;
					unset ($registrations[$key]);
					continue;
				}
			}

			// Set the description for the invoice
			$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
			$registrations[$key]['Event']['payment_desc'] = $event_obj->longDescription($registration);
		}
		// Reset the array to 0-indexed keys
		$registrations = array_values ($registrations);

		$this->set(compact ('registrations', 'full', 'person'));

		if ($op == 'payment') {
			$this->render ('payment');
		}
	}

	function unregister() {
		$id = $this->_arg('registration');
		if (!$id && empty($this->data)) {
			$this->Session->setFlash(__('Invalid registration', true));
			$this->redirect(array('action' => 'checkout'));
		}
		$this->Registration->contain (array(
			'Event' => array('EventType'),
			'Response',
		));
		$registration = $this->Registration->read(null, $id);

		if ($registration['Registration']['payment'] == 'Paid' && $registration['Event']['cost'] > 0) {
			$this->Session->setFlash(__('You have already paid for this! Contact the office to arrange a refund.', true));
			$this->redirect(array('action' => 'checkout'));
		}
		if ($registration['Registration']['payment'] == 'Refunded') {
			$this->Session->setFlash(__('You have already received a refund for this. Refunded records are kept on file for accounting purposes.', true));
			$this->redirect(array('action' => 'checkout'));
		}

		if (!$this->is_admin && $registration['Registration']['person_id'] != $this->Auth->user('id')) {
			$this->Session->setFlash(__('You may only unregister from events that you have registered for!', true));
			$this->redirect(array('action' => 'checkout'));
		}

		// Wrap the rest in a transaction, for safety.
		$transaction = new DatabaseTransaction($this->Registration);

		if ($this->Registration->delete()) {
			$success = true;
			$this->Session->setFlash(__('Successfully unregistered from this event.', true));

			// Check if anything else must be removed as a result (e.g. team reg after removing membership)
			while ($this->_unregisterDependencies()) {}

			$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
			if ($registration['Registration']['payment'] == 'Paid') {
				if (!$event_obj->unpaid($registration, $registration)) {
					$success = false;
					$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
				}
			}
			if (!$event_obj->unregister($registration, $registration)) {
				$success = false;
				$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
			}

			if ($success) {
				$transaction->commit();
			}
		} else {
			$this->Session->setFlash(__('Failed to unregister from this event!', true));
		}

		$this->redirect(array('action' => 'checkout'));
	}

	function _unregisterDependencies() {
		// Get everything from the user record that the decisions below might need
		$this->Registration->Person->contain (array (
			'Registration' => array(
				'Event' => array('EventType'),
				'Response',
				'conditions' => array('payment !=' => 'Refunded'),
			),
		));
		$person = $this->Registration->Person->read(null, $this->Auth->user('id'));
		$unregistered = false;

		// Pull out the list of unpaid registrations; these are the ones that might be removed
		$unpaid = Set::extract ('/Registration[payment!=Paid]/.', $person);

		foreach ($unpaid as $key => $registration) {
			// Check the registration rule, if any
			if (!empty ($registration['Event']['register_rule'])) {
				$rule_obj = AppController::_getComponent ('Rule');
				if ($rule_obj->init ($registration['Event']['register_rule']) &&
					!$rule_obj->evaluate ($person))
				{
					$this->Registration->delete($registration['id']);
					$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
					$event_obj->unregister($registration, $registration);
					unset ($person['Registration'][$key]);
					$unregistered = true;
				}
			}
		}

		return $unregistered;
	}

	function payment() {
		$this->layout = 'bare';
		$payment = $this->_getComponent ('payment', Configure::read('payment.payment_implementation'), $this);
		list ($result, $audit, $registration_ids) = $payment->process ($this->params['form']);
		if ($result) {
			$errors = array();

			$this->Registration->contain (array(
				'Person',
				'Event' => array('EventType'),
				'Response',
			));
			$registrations = $this->Registration->find ('all', array(
				'conditions' => array('Registration.id' => $registration_ids),
			));
			if (!$this->Registration->updateAll (
				array('Registration.payment' => '"Paid"'),
				array('Registration.id' => $registration_ids)
			))
			{
				$errors[] = sprintf (__('Your payment was approved, but there was an error updating your payment status in the database. Contact the office to ensure that your information is updated, quoting order #<b>%s</b>, or you may not be allowed to be added to rosters, etc.', true), $audit['order_id']);
			}

			foreach ($registration_ids as $id) {
				$this->Registration->RegistrationAudit->create();
				if (!$this->Registration->RegistrationAudit->save (array_merge($audit, array('registration_id' => $id)))) {
					$errors[] = sprintf (__('There was an error updating the audit record in the database. Contact the office to ensure that your information is updated, quoting order #<b>%s</b>, or you may not be allowed to be added to rosters, etc.', true), $audit['order_id']);
				}
			}

			// Wrap the rest in a transaction, for safety. The updates above are
			// intentionally excluded from this, as we always want as much of that
			// saved as possible. Missing team records can easily be added later;
			// missing payments take more work to track down.
			$transaction = new DatabaseTransaction($this->Registration);

			// Do any event payment processing
			$success = true;
			foreach ($registrations as $registration) {
				$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
				$extra = $event_obj->paid($registration, $registration);
				if ($extra) {
					if (is_array ($extra)) {
						// Manually add the event id to all of the responses :-(
						foreach (array_keys ($extra) as $key) {
							$extra[$key]['event_id'] = $registration['Event']['id'];
						}
						$success = $this->Registration->Response->saveAll($extra, array('atomic' => false, 'validate' => false));
					}
				} else if ($extra === false) {
					$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
					$success = false;
				}
				if (!$success) {
					$this->Session->setFlash(sprintf (__('There was an error updating the database. Contact the office to ensure that your information is updated, quoting order #<b>%s</b>, or you may not be allowed to be added to rosters, etc.', true), $registration['Registration']['id']));
					break;
				}
			}

			if ($success) {
				$transaction->commit();
			}
		}
		$this->set (compact ('result', 'audit', 'registrations', 'errors'));
		$this->Session->delete ('Zuluru.Unpaid');
	}

	function edit() {
		$id = $this->_arg('registration');
		if (!$id && empty($this->data)) {
			$this->Session->setFlash(__('Invalid registration', true));
			$this->redirect('/');
		}
		$this->Registration->contain (array(
			'Person',
			'Event' => array(
				'EventType',
				'Questionnaire' => array(
					'Question' => array(
						'Answer' => array(
							'conditions' => array('active' => true),
						),
						'conditions' => array('active' => true),
					),
				),
			),
			'Response',
		));
		$registration = $this->Registration->read(null, $id);
		if ($registration === false) {
			$this->Session->setFlash(__('Invalid registration', true));
			$this->redirect('/');
		}

		$event_obj = $this->_getComponent ('EventType', $registration['Event']['EventType']['type'], $this);
		$this->_mergeAutoQuestions ($registration, $event_obj, $registration['Event']['Questionnaire'], $registration['Person']['id']);
		$this->set(compact('registration'));

		if (!empty($this->data)) {
			$this->Registration->Response->validate = array_merge(
				$this->Questionnaire->validation($registration['Event']['Questionnaire'], true),
				$event_obj->registrationFieldsValidation ($registration)
			);

			// Remove any unchecked checkboxes; we only save the checked ones.
			list ($data, $delete) = $this->_splitResponses ($this->data);

			// This is all a little fragile, because of the weird format of the data we're saving.
			// We need to first set the response data, then validate it.  We can't rely on
			// Registration->saveAll to validate properly.
			$this->Registration->Response->set ($data);

			if (!$this->Registration->Response->validates()) {
				$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
				return;
			}

			// Wrap the whole thing in a transaction, for safety.
			$transaction = new DatabaseTransaction($this->Registration);

			// Use array_values here to get numeric keys in the data to be saved
			if (is_array($data) && array_key_exists('Response', $data)) {
				$data['Response'] = array_values($data['Response']);
			}

			// If the payment status has changed, we may need to do extra processing
			$paid = array('Paid', 'Pending');
			$was_paid = in_array ($registration['Registration']['payment'], $paid);
			$now_paid = in_array ($data['Registration']['payment'], $paid);
			if (!$was_paid && $now_paid) {
				// When it's marked as paid, the responses that the event object
				// should use are the new ones just now submitted.
				$result = $event_obj->paid($registration, $data);
				if (!$result) {
					$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
					return;
				}
				if (is_array ($result)) {
					$data['Response'] = array_merge($data['Response'], $result);
				}
			} else if ($was_paid && !$now_paid) {
				// When it's marked as unpaid, the responses that the event object
				// should use are the saved ones.
				$result = $event_obj->unpaid($registration, $registration);
				if (!$result) {
					$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
					return;
				}
				if (is_array ($result)) {
					$delete = array_merge ($delete, $result);
				}
			}

			// TODO: Redo the event registration, in case anything has changed. But
			// how will this interact with the payment status change handling above?
			$result = true; //$event_obj->reregister($registration, $data);
			if (!$result) {
				$this->Session->setFlash(__('Failed to perform additional registration-related operations.', true));
				return;
			}

			// Now manually add the event id to all of the responses :-(
			if (is_array($data) && array_key_exists('Response', $data)) {
				foreach (array_keys ($data['Response']) as $key) {
					$data['Response'][$key]['event_id'] = $registration['Event']['id'];
				}
			}

			if (!$this->Registration->saveAll($data, array('validate' => false))) {
				$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
				return;
			}

			// Remove any old response records that are no longer valid
			if (!empty($delete)) {
				if (!$this->Registration->Response->deleteAll (array(
					'id' => $delete,
					), false))
				{
					$this->Session->setFlash(__('The registration could not be saved. Please, try again.', true));
					return;
				}
			}

			if ($transaction->commit() !== false) {
				$this->Session->setFlash(__('The registration has been saved', true));
				$this->redirect(array('controller' => 'people', 'action' => 'registrations', 'person' => $registration['Person']['id']));
			}
		} else {
			// Convert saved response data into the format required by the output
			$this->data = $registration;
			$responses = array();
			foreach ($registration['Event']['Questionnaire']['Question'] as $question) {
				if (array_key_exists ('id', $question)) {
					$saved = Set::extract ("/Response[question_id={$question['id']}]", $registration);
					if (!empty ($saved)) {
						if ($question['type'] == 'checkbox') {
							foreach ($question['Answer'] as $answer) {
								$id = Set::extract ("/Response[answer_id={$answer['id']}]", $saved);
								if (!empty ($id)) {
									$responses[Question::_formName($question, $answer)] = $id[0]['Response'];
								}
							}
						} else {
							$responses[Question::_formName($question)] = $saved[0]['Response'];
						}
					}
				}
			}
			$this->data['Response'] = $responses;
		}
	}

	function preregistrations() { // TODO
	}

	function unpaid() {
		$this->Registration->contain (array(
			'Event' => array('EventType'),
			'Person',
		));
		$registrations = $this->Registration->find('all', array(
				'conditions' => array(
					'Registration.payment' => array('Unpaid', 'Pending'),
				),
				'order' => array('Registration.payment', 'Registration.modified'),
		));

		$this->set(compact('registrations'));
	}

	function _mergeAutoQuestions($event, $event_obj, &$questionnaire, $user_id = null, $for_output = false) {
		if (!array_key_exists ('Question', $questionnaire)) {
			$questionnaire['Question'] = array();
		}
		$questionnaire['Question'] = array_merge (
			$questionnaire['Question'], $event_obj->registrationFields($event, $user_id, $for_output)
		);
	}

	function _splitResponses($data) {
		// Make a list of old entries that now have answer_id = 0 (to delete)
		$delete = Set::extract ('/Response[answer_id=0][id>0]/id', $data);

		// Next, we remove any new checkbox entries with answer_id = 0 (not to be saved)
		if (is_array($data) && array_key_exists('Response', $data)) {
			foreach ($data['Response'] as $key => $response) {
				if (strpos ($key, 'a') !== false && $response['answer_id'] === '0') {
					unset ($data['Response'][$key]);
				}
			}
		}

		return array($data, $delete);
	}
}
?>
