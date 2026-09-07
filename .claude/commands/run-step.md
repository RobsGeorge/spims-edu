# /run-step — run a single step in a worktree

Usage: `/run-step <step-id>`  
Example: `/run-step A2-c`

## What this does

1. Looks up `<step-id>` in `prompts/steps.tsv` to confirm it is a known step
2. Composes the prompt: `prompts/GLOBAL-CONTRACT.md` + `prompts/step-<step-id>.md`
3. Runs the agent headless in a worktree:
   ```bash
   claude -p "$(cat prompts/GLOBAL-CONTRACT.md prompts/step-${STEP_ID}.md)" \
     --worktree "${STEP_ID}" \
     --permission-mode acceptEdits \
     --fallback-model sonnet
   ```
4. On completion, runs `./scripts/validate-step.sh <step-id>`
5. Reports pass/fail and the branch name (`worktree-<step-id>`)

## After passing
Review `logs/<step-id>.log` and the branch diff, then merge or note issues.

## Cleanup after merge
```bash
git worktree unlock .claude/worktrees/<step-id> 2>/dev/null || true
git worktree remove .claude/worktrees/<step-id> --force
git branch -d worktree-<step-id>
```
