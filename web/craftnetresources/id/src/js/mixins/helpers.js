/* global Craft */

import get from 'lodash/get'
import update from 'lodash/update'
import Vue from 'vue'
import Vuex from 'vuex'

Vue.use(Vuex)
Vue.use(require('vue-moment'))

var VueApp = new Vue();

export default {
    methods: {
        /**
         * Clones an object without references or bindings.
         * Optionally accepts a filtered property list with dot-syntax
         * for nested properties.
         *
         * Example:
         * ```
         * let obj = {
         *     test: 'value',
         *     foo: {
         *         bar: {
         *             baz: 'one',
         *             boo: 'two'
         *         }
         *     }
         * }
         *
         * // an existing value and a missing value, with default
         * let clone = simpleClone(obj, [
         *     'foo.bar.baz',
         *     ['aList', []]
         * ])
         *
         * clone == {foo: {bar: {baz: 'hello'}}, aList: []} // true
         * ```
         *
         * @param {Object} obj
         * @param {Array} propertyList
         */
        simpleClone(obj, propertyList) {
            let clone = JSON.parse(JSON.stringify(obj))

            if (!propertyList) {
                return clone
            }

            let filteredClone = {}

            for (let i = 0; i < propertyList.length; i++) {
                const path = propertyList[i];

                if (typeof path === 'object') {
                    update(filteredClone, path, () => get(clone, path[0], path[1]))
                } else {
                    update(filteredClone, path, () => get(clone, path, null))
                }
            }

            return filteredClone;
        },

        /**
         * Returns a static image URL.
         *
         * @param {String} url
         * @returns {String}
         */
        staticImageUrl(url) {
            if (process.env.NODE_ENV === 'development') {
                return process.env.BASE_URL + 'img/static/' + url;
            }

            return '/craftnetresources/id/dist/img/static/' + url;
        },

        /**
         * Returns the Craft Plugins URL.
         *
         * @returns {String}
         */
        craftPluginsUrl() {
            return process.env.VUE_APP_CRAFT_PLUGINS_URL;
        },

        loadAuthenticatedUserData(cb, cbError) {
            // Account
            this.$store.dispatch('craftId/getCraftIdData')
                .then(() => {
                    // Cart
                    this.$store.dispatch('cart/getCart')
                        .then(() => {
                            this.$store.commit('app/updateLoading', false)

                            if (cb) {
                                cb();
                            }

                            // Stripe Account
                            if (window.stripeAccessToken) {
                                this.$store.dispatch('account/getStripeAccount')
                                    .then(() => {
                                        this.$store.commit('app/updateStripeAccountLoading', false)
                                    }, () => {
                                        this.$store.commit('app/updateStripeAccountLoading', false)
                                    });
                            } else {
                                this.$store.commit('app/updateStripeAccountLoading', false)
                            }
                        })
                        .catch(() => {
                            if (cbError) {
                                cbError();
                            }
                        })

                    // Launch timer again now that we have a current user
                    this.$root.$children[0].$refs.authManager.updateRemainingSessionTime(Craft.remainingSessionTime);
                });
        },

        loadGuestUserData() {
            // Cart
            this.$store.dispatch('cart/getCart')
                .then(() => {
                    this.$store.commit('app/updateLoading', false)
                })
        },

        expiresSoon(license) {
            if(!license.expiresOn) {
                return false
            }

            const today = new Date()
            let expiryDate = new Date()
            expiryDate.setDate(today.getDate() + 45)

            const expiresOn = new Date(license.expiresOn.date)

            if(expiryDate > expiresOn) {
                return true
            }

            return false
        },

        daysBeforeExpiry(license) {
            const today = new Date()
            const expiresOn = new Date(license.expiresOn.date)
            const diff = expiresOn.getTime() - today.getTime()
            const diffDays = Math.round(diff / (1000 * 60 * 60 * 24))
            return diffDays;
        },

        renewableLicenses(license, renew) {
            let renewableLicenses = []

            // CMS license
            const expiryDateOptions = license.expiryDateOptions
            let expiryDate = expiryDateOptions[renew][1]

            renewableLicenses.push({
                type: 'cms-renewal',
                key: license.key,
                description: 'Craft ' + license.editionDetails.name,
                renew: renew,
                expiryDate: expiryDate,
                expiresOn: license.expiresOn,
                edition: license.editionDetails,
            })

            // Plugin licenses
            if (license.pluginLicenses.length > 0) {
                // Renewable plugin licenses
                const renewablePluginLicenses = license.pluginLicenses.filter(pluginLicense => {
                    // Plugin licenses with no `expiresOn` are not renewable
                    if (!pluginLicense.expiresOn) {
                        return false
                    }

                    // Ignore the plugin license if it expires after the CMS license
                    if (!pluginLicense.expired) {
                        const pluginExpiresOn = VueApp.$moment(pluginLicense.expiresOn.date)
                        const expiryDateObject = VueApp.$moment(expiryDate)

                        if(expiryDateObject.diff(pluginExpiresOn) < 0) {
                            return false
                        }
                    }

                    return true
                })

                // Add renewable plugin licenses to the `renewableLicenses` array
                renewablePluginLicenses.forEach(function(renewablePluginLicense) {
                    renewableLicenses.push({
                        type: 'plugin-renewal',
                        key: renewablePluginLicense.key,
                        description: renewablePluginLicense.plugin.name,
                        expiryDate: expiryDate,
                        expiresOn: renewablePluginLicense.expiresOn,
                        edition: renewablePluginLicense.edition,
                    })
                })
            }

            return renewableLicenses
        },
    }
}
