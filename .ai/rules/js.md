---
paths:
  - resources/js/e2eeChatSession.js
  - 'resources/js/e2ee/**'
  - 'resources/js/**/*.js'
---

# Js

## Re-bootstrap chat on tab resume
Open conversation threads listen for visibilitychange (hidden→visible) and bfcache pageshow, then call bootstrap() again to re-establish the E2EE session, HTTP-catch up messages, and resubscribe Echo. Do not remove this; WebSocket events are not replayed after mobile/background disconnects. Soft-resume keeps ready=true so the message list does not flash.

## Navigate on auth redirect/401
App chat/E2EE fetches must use appFetch from resources/js/http.js (or call followResponseRedirect). When Laravel redirects (response.redirected) or /api/* returns 401, navigate with window.location.assign instead of treating the response as app JSON. Echo private-channel auth uses the same helper in bootstrap.js.
