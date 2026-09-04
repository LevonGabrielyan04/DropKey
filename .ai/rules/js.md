---
paths:
  - resources/js/e2eeChatSession.js
  - resources/js/e2ee/**
---

# Js

## Re-bootstrap chat on tab resume
Open conversation threads listen for visibilitychange (hidden→visible) and bfcache pageshow, then call bootstrap() again to re-establish the E2EE session, HTTP-catch up messages, and resubscribe Echo. Do not remove this; WebSocket events are not replayed after mobile/background disconnects. Soft-resume keeps ready=true so the message list does not flash.
