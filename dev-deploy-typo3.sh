# This will spin up a docker container with typo3 and execute the cli setup
#
# always remove container -> typo3 distribution needs to be tested on empty install
# -> https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/HowTo/CreateNewDistribution.html#distribution-testing
# user www-data required -> base image chowns everything with it

container="gd-portal-typo3-headless"
ext_folder="$container:/var/www/html/typo3conf/ext"

docker compose stop
docker compose rm -f
docker compose up -d

docker exec -it gd-portal-typo3-headless chown -R www-data:www-data /var/www/html/typo3temp/var
docker exec -it gd-portal-typo3-headless chmod 777 /var/www/html/typo3temp/var

docker exec -it --user www-data $container sh -c "cd /var/www/html/typo3/sysext/core/bin && ./typo3 setup -n"

# copy required extensions
docker cp src/ansible/ext/headless.tar $ext_folder/headless.tar
docker cp src/ansible/ext/blog.tar $ext_folder/blog.tar
docker cp src/ansible/ext/ns_headless_blog.tar $ext_folder/ns_headless_blog.tar

# copy gd-site folder, but exclude composer-generated dirs
# additionally, rename data.dev.xml to data.xml to load it automatically. It contains example content pages which we want to have in local development, but not in other contexts
archive="gd-site-archive.tar"
tar --transform='flags=r;s|data.dev.xml|data.xml|' --exclude='gd-site/Initialisation/data.xml' --exclude='gd-site/public' --exclude='gd-site/vendor' -cf $archive gd-site/
docker cp $archive $ext_folder
docker exec -it --user www-data $container sh -c "cd /var/www/html/typo3conf/ext/ && tar xf gd-site-archive.tar"
rm $archive

# unpack extension tars
docker exec -it --user www-data $container sh -c "cd /var/www/html/typo3conf/ext/ && \
                                                          tar xf headless.tar && \
                                                          tar xf blog.tar && \
                                                          tar xf ns_headless_blog.tar"
# activate all extensions
docker exec -it --user www-data $container sh -c "cd /var/www/html/typo3/sysext/core/bin && \
                                                          ./typo3 extension:activate headless && \
                                                          ./typo3 extension:activate blog && \
                                                          ./typo3 extension:activate ns_headless_blog && \
                                                          ./typo3 extension:activate gd-site && \
                                                          ./typo3 cache:flush"