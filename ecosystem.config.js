module.exports = {
  apps: [
    {
      name: 'ifotoset-queue',
      cwd: './backend',
      script: '/www/server/php/84/bin/php',
      args: 'artisan queue:work --queue=photos,default --sleep=3 --tries=3 --timeout=300 --max-time=3600',
      exec_mode: 'fork',
      instances: 3,
      max_memory_restart: '450M',
      autorestart: true,
      watch: false
    },
    {
      // Video transcoding + HLS packaging (ProcessVideoJob runs on the "media" queue; needs ffmpeg on the host)
      name: 'ifotoset-video-queue',
      cwd: './backend',
      script: '/www/server/php/84/bin/php',
      args: 'artisan queue:work --queue=media --sleep=3 --tries=2 --timeout=3600 --max-time=7200',
      exec_mode: 'fork',
      instances: 1,
      max_memory_restart: '450M',
      autorestart: true,
      watch: false
    }
    {
      // Runs routes/console.php schedules (subscription expiry, payment reconcile, reminders...).
      // Use this OR a `* * * * * php artisan schedule:run` cron, never both.
      name: 'ifotoset-scheduler',
      cwd: './backend',
      script: '/www/server/php/84/bin/php',
      args: 'artisan schedule:work',
      exec_mode: 'fork',
      instances: 1,
      max_memory_restart: '200M',
      autorestart: true,
      watch: false
    }
  ]
};
