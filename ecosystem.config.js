module.exports = {
  apps: [
    {
      name: 'ifotoset',
      cwd: './frontend',
      script: 'npm',
      args: 'run start -- -H 127.0.0.1 -p 3004',
      exec_mode: 'fork',
      instances: 1,
      autorestart: true,
      watch: false,
      env: {
        NODE_ENV: 'production'
      }
    },
    {
      name: 'ifotoset-queue',
      cwd: './backend',
      script: '/www/server/php/84/bin/php',
      args: 'artisan queue:work --sleep=3 --tries=3 --timeout=300 --max-time=3600',
      exec_mode: 'fork',
      instances: 1,
      autorestart: true,
      watch: false
    }
  ]
};
