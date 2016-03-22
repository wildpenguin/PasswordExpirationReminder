# PasswordExpirationReminder

This small piece of code is part of a bigger application I built and I post it here only to share several interesting ideas: working with dates. The users in our company are using non windows workstations and it happens that their password expires without being aknowledged every 6 months, as required by the AD policy. The Windows accounts are used basically for emails and other services. The solution was to add all these users to a group and wait one week before  the password expiration to send a reminder by email. The application took some of the workload from our "always busy" Windows admins. They are happy now. 

The following code will lack the MyLDAP, Account, CUtil classes:
MyLDAP will connect to our AD in "a kind of" API layer. Nothing complicated, the standard PHP commands with some throws here and there.
Account is the class to work specifically with ldap users objects.
CUtil is a mainly static class that I call to do some dirty job: formating strings and other common stuff, I share it among all my classes. 

