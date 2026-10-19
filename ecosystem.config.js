module.exports = {
  apps: [
    {
      name: 'ifotoset-queue',
      cwd: '.',
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
      cwd: '.',
      script: '/www/server/php/84/bin/php',
      args: 'artisan queue:work --queue=media --sleep=3 --tries=2 --timeout=3600 --max-time=7200',
      exec_mode: 'fork',
      instances: 1,
      max_memory_restart: '450M',
      autorestart: true,
      watch: false
    }
    // The scheduler (routes/console.php) runs from root's crontab, not pm2:
    //   * * * * * /www/server/php/84/bin/php /www/wwwroot/ifotoset.com/artisan schedule:run
    // Never add a `schedule:work` app here as well, or every scheduled job runs twice.
  ]
};
