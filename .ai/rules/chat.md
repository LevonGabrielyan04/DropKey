---
paths:
  - 'resources/views/chat/**/*.blade.php'
  - resources/views/chat/index.blade.php
---

# Chat

## Conversation settings live on Chat Settings
Partner fingerprint verification and auto-delete live on the per-conversation Chat Settings page (`chat.settings`), not the chat thread. Every chat show page must link to Chat Settings. Do not put those controls back on `chat.show`.

## Truncate inbox partner names
Recent-conversation partner names must use min-w-0 truncate (and min-w-0 on the flex ancestors) so long names ellipsize instead of clipping the timestamp on narrow screens. Keep the timestamp/unread badge shrink-0. Set title to the full partner name.
