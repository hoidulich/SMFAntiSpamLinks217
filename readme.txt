Anti-Spam Links for SMF 2.1.x
================================

This package ports the classic Anti-Spam Links mod from SMF 2.0.x to SMF 2.1.x.

What it does
------------
- Blocks external links for low-post members if configured.
- Rewrites external links as non-clickable text for low-post members if configured.
- Adds rel="nofollow" to external links for low-post members if configured.
- Supports guest-specific handling.
- Applies to post preview, quick edit XML responses, displayed posts, topic summaries, and member signatures.

How this port differs from the old 2.0.x package
------------------------------------------------
- Uses SMF 2.1 integration hooks instead of editing SMF core files directly.
- Adds a Vietnamese language file in addition to English.
- Keeps settings under:
  Admin > Posts and Topics > Posts

Settings
--------
- Post count under which members cannot post external links
- Post count under which members external links are shown [nonactive]
- Post count under which members external links are set [nofollow]
- Guest behavior:
  - disable mod for guests
  - cannot post links
  - links are shown [nonactive]
  - links are set [nofollow]

Notes
-----
- Internal links to your forum are excluded.
- If both "nonactive" and "nofollow" thresholds are enabled, the package ensures the nofollow threshold stays above the nonactive threshold.

