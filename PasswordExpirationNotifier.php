<?php 

class PasswordExpirationNotifierCommand extends CConsoleCommand 
{
	public function actionIndex()
	{
		$this->runNotifier();
	}

	public function runNotifier()
	{
		$ldap = new myLDAP;
		$accounts = $this->getNonWindowsAccounts($ldap);
		$expiringAccounts = $this->getExpiringAccounts($accounts);
		$this->notifyAccount($expiringAccounts);
	}

	/**
	* Get all the non-windows accounts
	* that password expire and need to renew before the expiration date
	*/

	public function getNonWindowsAccounts($ldap)
	{

		$ldap->filter = '(&(objectclass=group)(cn=*-SG-Not Windows*))';
		$ldap->justthese = array('member');
		$groups = $ldap->Search();

		if(empty($groups))
			return;

		$members = array();
		foreach ($groups as $key => $value)
		{
			if(
				is_int($key) && 
				is_array($value) && 
				array_key_exists('member', $value)
			)
				$members = array_merge($members, $value['member']);
		}
		
		$filtered = array();
		foreach($members as $key => $value)
		{
			if(
				is_int($key) &&
				CUtil::isADSpecialAccount($value) == false && 
				CUtil::getAccountLocation($value) != 'TST'
			)
				$filtered[] = $value;
		}
		return array_unique($filtered);
	}

	/**
	* Check the users that password is expiring soon
	*
	*/
	public function getExpiringAccounts($accounts)
	{

		if(empty($accounts))
			return;
		
		$account = new Account;
		$account->setLoadAttributes(array(
			'distinguishedname', 'userprincipalname', 'pwdlastset', 'displayname'
		));
		$expiringAccount = array();
		foreach($accounts as $key => $value)
		{
			$account->loadAccountByDN($value);
			if($account->ldapattr)
			{
				$account->ldapattr->daysPasswordExpire = $account->daysPasswordExpire();
				if(
					$account->ldapattr->daysPasswordExpire > time() && // exclude expired accounts
					(
						date('Ymd', $account->ldapattr->daysPasswordExpire) == date('Ymd', strtotime("+ 7 days")) ||
						date('Ymd', $account->ldapattr->daysPasswordExpire) == date('Ymd', strtotime("+ 5 days")) ||
						date('Ymd', $account->ldapattr->daysPasswordExpire) == date('Ymd', strtotime("+ 3 days")) ||
						date('Ymd', $account->ldapattr->daysPasswordExpire) == date('Ymd', strtotime("+ 2 days")) ||
						date('Ymd', $account->ldapattr->daysPasswordExpire) == date('Ymd', strtotime("+ 1 days"))
					)
				)
				{ $expiringAccounts[] = $account->ldapattr; }
			}
		}
		return $expiringAccounts;
	}

	/**
	* Notify accounts 
	*
	*/
	public function notifyAccount($expiringAccounts)
	{
		$path = Yii::getPathOfAlias('application.views.account');
		$subject = '[GNS] Votre mot de passe expire bientôt! / Your password is expiring soon!';
		foreach($expiringAccounts as $account)
		{
			$expireDate = date(DATE_RFC2822, $account->daysPasswordExpire);
			$daysLeft = round(($account->daysPasswordExpire - time())/(3600 * 24), 0);

		    $body = $this->renderFile($path . '/passwordNotifier.php', array(
            	'account'=>$account, 
            	'expireDate'=>$expireDate, 
            	'daysLeft' => ($daysLeft == 0 )? ' < 1' : $daysLeft,
            	'passwordUrl'=>'https://myapp.org/account/password/id/'.
            			 CUtil::removeEmailPart($account->userprincipalname)), 
		    true);
            $to = $account->userprincipalname;
            CUtil::sendEmail($to, $subject, $body);

    	}
	}

}


?>