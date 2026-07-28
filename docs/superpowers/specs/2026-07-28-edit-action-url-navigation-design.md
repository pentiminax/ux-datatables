# Edit Action URL Navigation

## Goal

When an `EDIT` action defines a URL through `linkToUrl()`, clicking the rendered action must use
normal browser navigation instead of opening the inline edit modal.

## Behavior

- An `EDIT` action with a safe resolved URL renders as an anchor.
- Browser navigation uses the current tab by default.
- Configured HTML attributes remain on the anchor, including `target`, `rel`, and accessibility
  attributes.
- An `EDIT` action without a resolved URL remains a button and keeps the existing modal workflow.
- Unsafe URLs are not rendered.
- Existing behavior for `DETAIL`, `CUSTOM`, and `DELETE` actions remains unchanged.

## Implementation

Update the action column renderer to include `EDIT` actions in its link-rendering path only when a
URL is resolved. Keep URL resolution and unsafe-URL validation at the existing rendering boundary.
Do not add imperative navigation to the Stimulus controller.

The generated anchor keeps `data-action-type="EDIT"` for consistency but does not carry the
entity and row identifier used by the modal workflow. The delegated click handler can therefore
process confirmation when configured and otherwise lets the browser follow the link.

## Verification

Add frontend regression coverage proving that:

- an `EDIT` action with a static URL renders a link;
- an `EDIT` action with a per-row resolved URL renders a link;
- configured HTML attributes are preserved;
- an `EDIT` action without a URL remains a button;
- unsafe URLs are rejected.

Run the targeted Vitest suite, then the frontend lint, typecheck, full test, and build checks.
