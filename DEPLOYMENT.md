# Negarify Backend - Deployment Guide

## Queue Workers Setup

### Supervisor Configuration

1. Install Supervisor (if not already installed):
```bash
sudo apt-get install supervisor
```

2. Copy the Supervisor configuration template:
```bash
sudo cp deployment/supervisor/negarify-worker.conf /etc/supervisor/conf.d/negarify-worker.conf
```

3. Edit the configuration file:
```bash
sudo nano /etc/supervisor/conf.d/negarify-worker.conf
```

Update the following placeholders:
- `/path/to/artisan` → Actual path to your Laravel artisan file (e.g., `/var/www/negarify-backend/artisan`)
- `/var/log/negarify/worker.log` → Desired log path (ensure directory exists)
- `www-data` → Appropriate user (usually `www-data` or your web server user)
- `numprocs=4` → Adjust based on server capacity (4 is default)

4. Create log directory (if needed):
```bash
sudo mkdir -p /var/log/negarify
sudo chown www-data:www-data /var/log/negarify
```

5. Reload Supervisor configuration:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start negarify-worker:*
```

6. Verify workers are running:
```bash
sudo supervisorctl status
```

You should see 4 worker processes running:
```
negarify-worker:negarify-worker_00   RUNNING   pid 12345, uptime 0:00:01
negarify-worker:negarify-worker_01   RUNNING   pid 12346, uptime 0:00:01
negarify-worker:negarify-worker_02   RUNNING   pid 12347, uptime 0:00:01
negarify-worker:negarify-worker_03   RUNNING   pid 12348, uptime 0:00:01
```

### Worker Management Commands

```bash
# Start workers
sudo supervisorctl start negarify-worker:*

# Stop workers
sudo supervisorctl stop negarify-worker:*

# Restart workers
sudo supervisorctl restart negarify-worker:*

# View worker logs
sudo tail -f /var/log/negarify/worker.log

# View worker status
sudo supervisorctl status negarify-worker:*
```

### Worker Configuration Details

- **Command**: `php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600`
  - `--sleep=3`: Wait 3 seconds when queue is empty (prevents CPU spinning)
  - `--tries=3`: Maximum retry attempts (handled by job classes)
  - `--max-time=3600`: Restart worker after 1 hour (prevents memory leaks)

- **Number of Workers**: 4 by default (adjust based on load)
  - More workers = higher throughput but more resource usage
  - Monitor queue length and adjust accordingly

- **Auto-restart**: Workers automatically restart on crash or after max-time

---

## Scheduled Tasks Setup

### Cron Configuration

Laravel's scheduler requires a single cron entry that runs every minute.

1. Edit crontab:
```bash
crontab -e
```

2. Add the following line (replace `/path-to-project` with actual path):
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Example:
```bash
* * * * * cd /var/www/negarify-backend && php artisan schedule:run >> /dev/null 2>&1
```

3. Verify cron is running:
```bash
# Check if cron service is running
sudo systemctl status cron

# View cron logs (if available)
grep CRON /var/log/syslog
```

### Verify Scheduled Tasks

1. List all registered scheduled tasks:
```bash
php artisan schedule:list
```

You should see:
- Currency rate fetch: every 5 minutes
- Feed view limit reset: daily at 00:00
- Analytics aggregation: daily at 01:00
- OTP cleanup: hourly
- Old job cleanup: daily at 02:00
- System health recording: every 5 minutes

2. Test scheduler manually:
```bash
php artisan schedule:run
```

3. Test individual tasks:
```bash
# Test currency rate fetch
php artisan schedule:run --task="App\\Console\\Kernel@schedule"

# Test feed view limit reset
php artisan feed:reset-view-limits

# Test analytics aggregation
php artisan analytics:aggregate-models-usage --period=daily

# Test OTP cleanup
php artisan otp:cleanup --hours=1

# Test old job cleanup
php artisan jobs:cleanup-old --days=90

# Test system health recording
php artisan system:record-health
```

### Scheduled Tasks Overview

| Task | Frequency | Time | Description |
|------|-----------|------|-------------|
| Currency Rate Fetch | Every 5 minutes | - | Fetches USD to Toman rate from TGJU.org |
| Feed View Limit Reset | Daily | 00:00 | Resets daily feed view limits for all users |
| Analytics Aggregation | Daily | 01:00 | Aggregates generation jobs into analytics_models_usage |
| OTP Cleanup | Hourly | - | Removes expired OTP verification records |
| Old Job Cleanup | Daily | 02:00 | Deletes generation jobs older than 90 days |
| System Health Recording | Every 5 minutes | - | Records system health metrics |

### Scheduled Task Reliability

- **withoutOverlapping()**: Prevents concurrent execution (mutex locks)
- **Error Handling**: All tasks wrapped in try-catch, errors logged
- **Idempotency**: Tasks can be safely re-run if they fail
- **Logging**: All tasks log start, completion, duration, records processed

---

## Verification Checklist

### Queue Workers
- [ ] Supervisor installed and running
- [ ] Supervisor config deployed
- [ ] Workers running (check with `supervisorctl status`)
- [ ] Workers processing jobs (check logs)
- [ ] Failed jobs handled correctly
- [ ] Token refunds working on failure

### Scheduled Tasks
- [ ] Cron job configured
- [ ] Scheduler running (check with `schedule:list`)
- [ ] All tasks registered
- [ ] Tasks executing (check logs)
- [ ] No overlapping execution
- [ ] Error handling working

### Monitoring
- [ ] Worker logs accessible
- [ ] Scheduler logs accessible
- [ ] Failed jobs table exists
- [ ] System health metrics being recorded
- [ ] Queue length monitored
- [ ] Alerting configured (if applicable)

---

## Troubleshooting

### Workers Not Running
```bash
# Check Supervisor status
sudo supervisorctl status

# Check Supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Check worker logs
sudo tail -f /var/log/negarify/worker.log

# Restart Supervisor
sudo systemctl restart supervisor
```

### Scheduler Not Running
```bash
# Check if cron is running
sudo systemctl status cron

# Check cron logs
grep CRON /var/log/syslog

# Test scheduler manually
php artisan schedule:run

# Check Laravel logs
tail -f storage/logs/laravel.log
```

### Queue Backlog
```bash
# Check queue length
php artisan queue:work --once

# Clear stuck jobs (use with caution)
php artisan queue:clear

# Retry failed jobs
php artisan queue:retry all
```

### High Memory Usage
- Reduce `numprocs` in Supervisor config
- Reduce `--max-time` (but not below longest job timeout)
- Monitor worker memory usage

---

## Production Recommendations

1. **Monitoring**: Set up monitoring for:
   - Queue length (alert if > 1000)
   - Failed job rate (alert if > 10%)
   - Worker status (alert if not running)
   - Scheduled task failures

2. **Logging**: Ensure log rotation is configured:
   - Worker logs: `/var/log/negarify/worker.log`
   - Laravel logs: `storage/logs/laravel.log`

3. **Scaling**: Adjust worker count based on:
   - Queue length
   - Server capacity
   - Job processing time

4. **Backup**: Regular backups of:
   - Database (generation_jobs, token_transactions)
   - Failed jobs table
   - Configuration files

---

## Security Notes

- Workers run as `www-data` (non-root user)
- Log files readable by appropriate users only
- No secrets in logs (API keys, tokens sanitized)
- Redis queue protected with authentication
- Supervisor config protected (read-only for workers)

