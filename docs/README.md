# Thiscovery Incentives

**Version 0.2.0**  
Copyright (c) 2026 D Cube Consulting. All rights reserved.

Pay survey participants and community members through Ayda. A manager authorises every payment before any money moves. Nothing is sent until that confirmation.

## Requirements

- HumHub 1.18 or newer
- PHP 8.2 or newer
- [Thiscovery Forms](https://github.com/gauravsdcube/thiscovery_forms) for survey rewards
- An Ayda API username and password

## Enable

1. Administration → Modules → Thiscovery Incentives → Enable
2. Administration → Thiscovery Incentives → Ayda settings. The address defaults to the production Ayda API, `https://technology.helloayda.com`. Save the username and password issued for that service, then test the connection. A saved demo address is moved to production, the cached sign-in is cleared, and any demo project link is dropped. Payments already released stay in the register and are not sent again
3. Enable the module on each space that should manage its own rewards
4. Grant **Manage incentives** at network level, or **Manage space incentives** in the space. Owners, admins, and moderators have the space permission by default

The password is stored encrypted. Leave it blank when saving settings to keep the current password.

## Survey reward

1. Open the form, go to Settings → Basic, and tick **Enable incentives**. The form needs email, first name, and last name questions
2. Incentives → **New survey reward**. Only forms with incentives turned on are listed, and a form that already has a reward is left out
3. Map the email, first name, and last name questions. For opt-in, also map the question and the answer that means yes
4. Refresh the list, untick anyone who should not be paid, and change an amount if needed
5. Add funds to the Ayda wallet for the reward currency. Review shows that balance, the fees, and VAT. Authorise stays blocked until the balance is shown and covers the total. Ayda then emails each person a link to claim the payment

A response without a valid email and an Ayda-valid name stays on the list and cannot be paid. People already on the list keep their own amounts when the default amount changes.

## Community reward

Incentives → **New community reward**. Choose the whole network, one space, or one user group. No form is required. Whole network does not ask for a space.

Refresh copies the members at that moment. A later refresh adds people who joined afterwards and updates names and emails that have not been sent. Existing ticks, amounts, and authorised rows stay.

## After a reward is created

**Edit reward** changes the title, who qualifies, the question mapping, the opt-in answer, the default amount, and, for a community reward, the audience. The currency stays once Ayda has opened the project. The form stays once anyone is on the list. The name people see when they claim stays the name Ayda stored.

## Register

Network managers see every payment. Space managers see payments for their space. Export writes a CSV and records who exported it, when, and how many rows. A member sees only their own payments under Account → My incentives.

Deleting a user clears the name, email, and phone, and asks Ayda to forget them. Amounts and the status history stay.

## License

AGPL-3.0-or-later — see `COPYRIGHT`.
