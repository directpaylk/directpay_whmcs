#!/bin/bash

GROUP="$DEPLOYMENT_GROUP_NAME"
FOLDER_NAME="whmcs"
DEV_STAGE="whmcs_dev"
LOG_FILE="/var/www/"$FOLDER_NAME"/deploy.log"
dt=$(date '+%d/%m/%Y %H:%M:%S');
SLACK_HOOK_URL="https://hooks.slack.com/services/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"

echo "================================" >> $LOG_FILE
echo $dt >> $LOG_FILE
echo $GROUP >> $LOG_FILE
echo $FOLDER_NAME >> $LOG_FILE

curl -X POST -H 'Content-type: application/json' --data '{"footer":"Deployment","color":"warning","text":":passenger_ship: '$GROUP' AfterInstall-START"}' $SLACK_HOOK_URL
# copy vcs to location # move to location # Run composer - set permission - copy env
sudo rsync -av /home/ec2-user/vcs/$FOLDER_NAME /var/www/ &&
cd /var/www/$FOLDER_NAME &&
rm -rf /home/ec2-user/vcs/$FOLDER_NAME &&
curl -X POST -H 'Content-type: application/json' --data '{"footer":"Deployment","color":"warning","text":":passenger_ship: '$GROUP' AfterInstall-END"}' $SLACK_HOOK_URL
