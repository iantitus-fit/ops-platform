# Start here

This folder is how Example Heating & Air runs. Change a file and the business changes, once the change checks out.

## Changing something

1. Open the file that owns the fact. Hours are in `business/hours.md`, prices are in the file for that service in `business/services/`, and how we sound is in `voice/how-we-talk.md`.
2. Edit the text and save.
3. Run the sync (`php artisan icm:sync`) or click Sync Now on the Business Folder page of the dashboard.
4. Read `changelog.md`. It says whether your change went live, or what to fix first. If something is wrong, the previous version stays live, so customers never see a half-finished edit.

## What not to touch

`records/` is written by the system from what really happened. It is read-only: anything you type there is overwritten the next time a customer writes in.
