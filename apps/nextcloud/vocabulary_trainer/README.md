# Vocabulary Trainer for Nextcloud

Vocabulary Trainer helps you organize vocabulary in your Nextcloud account. Create a collection for a language pair, then add word pairs to it. Collections are private to your account.

## Using the app

1. Open **Vocabulary Trainer** from the Nextcloud app navigation.
2. Choose **Add collection** and enter a collection name, source and target languages, and the number of study boxes.
3. Select a collection by clicking its title to open its collection view.
4. Add word pairs with a source word or phrase and its translation. Notes are optional.
5. Use **Save and add another** to keep entering pairs without closing the dialog.
6. Choose **Start learning** in a collection to select a translation direction, question mode, and which pairs to practice. Automatic sessions include new and due pairs; targeted sessions can focus on new pairs, a box, an error-rate threshold, or pairs not practiced for a selected number of days.
7. Type answers or choose from multiple-choice options. Multiple choice and mixed modes require at least 11 matching pairs. You can optionally set a response time limit or leave it unlimited. An unanswered timeout is recorded as incorrect.
After each answer, the result stays visible for your chosen delay (five seconds by default) before the next question. Use the pin button to pause or resume either timer, or click the card outside its controls to move to the next step early.
8. Edit individual pairs or select several to move them to another collection or delete them.

Correct answers move a pair up one study box; incorrect answers return it to the first box. Reviews are scheduled at increasing intervals. Each collection dashboard shows its pair and box counts, answer accuracy across both translation directions, and normalized learning progress. The box distribution is split by direction; select a box to filter the displayed pairs.

## Your data and privacy

Collections and word pairs are stored in the Nextcloud instance where the app is installed. The app scopes collection operations to the signed-in account; another user cannot access your collections through the app.

For installation, testing, API details, and other contributor information, see the [development manual](../../../docs/development.md).
