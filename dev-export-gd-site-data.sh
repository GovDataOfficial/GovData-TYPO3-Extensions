# export the site configuration
# needs to be commited in repo if anything changes!

container="gd-portal-typo3-headless"

echo "Export to? (main / dev)"
read input

# Check the user's input and set the variable accordingly
if [ "$input" = "dev" ]; then
    config_file="data.dev.xml"
elif [ "$input" = "main" ]; then
    config_file="data.xml"
else
    echo "Invalid input. Exiting the script."
    exit 1
fi

docker exec -it $container sh -c "/var/www/html/typo3/sysext/core/bin/typo3 impexp:export data \
                                          --pid 1 \
                                          --levels 999 \
                                          --dependency headless \
                                          --dependency gd-extensions \
                                          --include-related '_ALL' \
                                          --table '_ALL' \
                                          --record '_ALL'"

docker cp $container:/var/www/html/fileadmin/user_upload/_temp_/importexport/data.xml ./gd-site/Initialisation/$config_file
