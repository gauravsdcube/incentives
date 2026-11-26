# Changelog

## 0.2.0 (November 26, 2026)

- Enh: The Ayda API address defaults to production at https://technology.helloayda.com. A saved demo address is moved across, the cached sign-in is cleared, and demo project and participant ids are dropped so the next payment opens a production project. Payments already released stay in the register and are not sent again.
- Fix: A name is checked against the Latin letters Ayda accepts, including accented letters and a curly apostrophe. Cambodian riel (KHR) is included in the currency list.
- Fix: Incentive status checks ask Ayda for the trust score. A rate-limit response is retried once when Ayda asks for no more than five seconds.
- Fix: Payment cannot be authorised until the Ayda wallet balance is shown and covers the total. Creating a reward tells the manager to add funds to that wallet.

## 0.1.0 (November 23, 2026)

- Enh: Survey and community incentive rewards, with manager authorisation before anything is sent to Ayda.
- Enh: Payment register with amount overrides, status history, CSV export, and removal of name and email when a user is deleted.
- Enh: A survey reward lists only forms with Enable incentives ticked, and a form that already has a reward is left out.
- Enh: A reward can be edited after it is created, including who qualifies and which questions supply the email and name.
- Fix: A community reward does not ask for a form, and whole network does not ask for a space.
- Fix: Ayda’s reason for refusing a payment is shown. If Ayda creates the project and then returns an error, the existing project is used.
