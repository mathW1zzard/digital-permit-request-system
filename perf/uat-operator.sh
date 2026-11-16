#!/usr/bin/env bash
# Week 17 UAT: the observer's operator steps, run on staging (called by perf/uat-run.mjs).
#   expire-sessions <email>  end that user's sessions now (UAT-10). Only that user: in round 1 it
#                            ended everyone's, which broke the next card (test-tool defect TD8).
#   fix-bounce-email       give the fictional "bad address" account a deliverable address (UAT-12)
#   restore-bounce-email   put the bad address back, so the demo case stays available
source "$(dirname "$0")/../deploy/staging/common.sh"
cd "$STAGING/current/backend"
case "${1:?step}" in
expire-sessions)
    EMAIL="${2:?expire-sessions <email>}"
    "$PHP" artisan tinker --execute="echo DB::table('personal_access_tokens')->where('tokenable_id', App\Models\User::where('email', '$EMAIL')->value('id'))->update(['expires_at' => now()->subMinute()]);" ;;
fix-bounce-email)
    "$PHP" artisan tinker --execute="echo App\Models\User::where('email','bounce@invalid.test')->update(['email' => 'bounce-fixed@permit.test']);" ;;
restore-bounce-email)
    "$PHP" artisan tinker --execute="echo App\Models\User::where('email','bounce-fixed@permit.test')->update(['email' => 'bounce@invalid.test']);" ;;
esac
log "uat-operator: $1"
