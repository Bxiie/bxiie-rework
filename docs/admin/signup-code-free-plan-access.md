# Free plan access signup codes

Platform Admin → Signup Codes can create a **Free access code**. This code type lets a prospective tenant create a site and choose any active pricing plan. ArtsFolio assigns the selected plan as a trial and records the free access end date.

## Create a code

1. Open `/platform/admin/signup-codes`.
2. Set **Code type** to **Free access code**.
3. Enter a label that identifies the campaign or person.
4. Optionally restrict the code to one recipient email.
5. Set the redemption limit.
6. Set **Free access months** to the number of months to waive platform plan billing.
7. Create the code and send the invite.

## Redemption behavior

When a free access code is entered at `/signup`, the signup form shows a plan selector containing every active plan. The tenant chooses the desired plan before submitting the signup form.

After signup, ArtsFolio creates a `tenant_plan_assignments` row with:

```text
status = trial
complimentary_until = current time plus the configured number of months
granted_by_signup_code_id = redeemed signup code id
billing_note = signup-code grant summary
```

The code is then marked redeemed according to its redemption limit.

## Operational notes

Free access codes waive platform plan billing for the stated period. They do not change payment processor fees, sales commission rules, fulfillment responsibility, or artwork sale economics.

<!-- End of file. -->
