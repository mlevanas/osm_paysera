<?php

defined('_JEXEC') or die;
use Joomla\CMS\Table\Table;

		require_once JPATH_SITE. '/components/com_osmembership/plugins/WebToPay.php';
class os_paysera extends MPFPayment
{

	protected $mode, $projectId, $projectPass;

	/**
	 * Constructor functions, init some parameter
	 *
	 * @param \Joomla\Registry\Registry $params
	 * @param array                     $config
	 */
	public function __construct($params, $config = array())
	{
		parent::__construct($params, $config);

		$this->url = 'https://bank.paysera.com/pay/';
		$this->mode = $params->get('mode');
		$this->projectId = $params->get('project_id');
		$this->projectPass = $params->get('project_password');
		// Additional constructor code goes here
	}

	/**
	 * Process Payment
	 *
	 * @param OSMembershipTableSubscriber $row
	 * @param array                       $data
	 */
	public function processPayment($row, $data)
	{
		/**
		 * Call $this->setParameter method to pass the data to your payment gateway. Each payment gateway requires
		 * different parameters, so please read your payment gateway manual for to see the data you have to pass to the payment gateway.
		 *
		 * Below are sample code:
		 */

        $itemId = JFactory::getApplication()->input->getInt('ItemId', 0);

		$data = [
			'amount' => round($data['amount'], 2) * 100,
			'currency_code', $data['currency'],
			'country' => 'LT',
			'callbackurl' => \Joomla\CMS\Uri\Uri::base() . 'index.php?option=com_osmembership&task=payment_confirm&payment_method=os_paysera',
			'cancelurl' => \Joomla\CMS\Uri\Uri::base() . 'index.php?option=com_osmembership&view=cancel&id='. $row->id. '&ItemId='. $itemId,
			'accepturl' => \Joomla\CMS\Uri\Uri::base() . 'index.php?option=com_osmembership&view=payment&layout=complete&subscription_code='. $row->subscription_code . '&ItemId='. $itemId, 
			'test' => $this->mode,
			'projectid' => $this->projectId,
			'sign_password' => $this->projectPass,
			'orderid' => $row->id
		];

		try {
			$request_data = WebToPay::buildRequest($data);
			$this->setParameter('data', $request_data['data']); //konvertuojami i centus
			$this->setParameter('sign', $request_data['sign']);
			$this->renderRedirectForm();
		} catch (\Exception $ex) {
			
			throw new Exception($ex->getMessage());
		}
	}

	/**
	 * Verify payment
	 *
	 * @return bool
	 */
	public function verifyPayment()
	{
	    
		$this->logGatewayData(sprintf('Transaction started'));
		if ($this->validate()) {
			$id            = $this->notificationData['orderid'];
			$transactionId = $this->notificationData['requestid'];

			$row = Table::getInstance('Subscriber', 'OSMembershipTable');

			if (!$row->load($id))
			{
			    	$this->logGatewayData(sprintf('Invalid Subscription ID %s', $id));
				return false;
			}

			// If the subsctiption is active, it was processed before, return false
			if ($row->published)
			{
		    	$this->logGatewayData(sprintf('Subscription is already active'));
		    	return false;
			}

			// Check and make sure the transaction is only processed one time
			if ($transactionId && OSMembershipHelper::isTransactionProcessed($transactionId))
			{
			    
		    	$this->logGatewayData(sprintf('Transaction %s already processed', $transactionId));
				return false;
			}

			// This will final the process, set subscription status to active, trigger onMembershipActive event, sending emails to subscriber and admin...
			$this->logGatewayData(sprintf('Transaction successful'));
			echo 'OK';
			$this->onPaymentSuccess($row, $transactionId);
		} 
		else{
		    $this->logGatewayData(sprintf('Transaction validation failed'));
			return false;
		}
		
	}

	// private function validateResponse($order, $response)
	// {
	// 	if (array_key_exists('payamount', $response) === false) {
	// 		if ($order['amount'] !== $response['amount'] || $order['currency'] !== $response['currency']) {
	// 			throw new Exception('Wrong payment amount');
	// 		}
	// 	} else {
	// 		if ($order['amount'] !== $response['payamount'] || $order['currency'] !== $response['paycurrency']) {
	// 			throw new Exception('Wrong payment amount');
	// 		}
	// 	}
	// }


	/**
	 * Validate the post data from Payment gateway to our server
	 *
	 * @return string
	 */
	protected function validate()
	{
		// Store data passed from payment gateway to the system to use it later
		$this->notificationData = $_POST;
		

		$response = WebToPay::validateAndParseData($this->notificationData, $this->projectId, $this->projectPass);

		if($response['status'] === '1' || $response['status'] === 3){
			return true;
		}
		else return false;
	}
}
