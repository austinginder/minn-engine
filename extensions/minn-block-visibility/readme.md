# Block Visibility for Minn

Honours the `blockVisibility` attribute the Block Visibility plugin stores on
blocks, so a site that used the plugin renders the same way on Minn Engine.
Written from the plugin's observed output, not its source; MIT.

Handled: `hideBlock`; control sets with `browserDevice` (device type from the
user agent: `mobile` covers phones and tablets, `other` is everything else, as
observed), `screenSize` (the hide classes and the breakpoint stylesheet), `userRole`
(`logged-in`, `logged-out`, `user-role` with `restrictedRoles`), and `dateTime`
schedules (`start`/`end` in site time). Other controls leave the block visible.
