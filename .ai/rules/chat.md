---
paths:
  - 'resources/views/chat/**/*.blade.php'
---

# Chat

## Conversation settings live on Chat Settings
Partner fingerprint verification and auto-delete live on the per-conversation Chat Settings page (`chat.settings`), not the chat thread. Every chat show page must link to Chat Settings. Do not put those controls back on `chat.show`.
