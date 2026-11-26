<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat test page that runs the browser checks of the network module.
 *
 * Prints one "PASS <name>" or "FAIL <name>: <detail>" line per check, then "DONE".
 *
 * @package    local_dixeo
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
defined('BEHAT_SITE_RUNNING') || die();
require_login();

$component = 'local_dixeo';

$PAGE->set_url(new moodle_url('/local/dixeo/tests/behat/fixtures/network_testpage.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Network module checks');

$config = [
    'module' => $component . '/network',
    'component' => $component,
    'missingmethod' => $component . '_does_not_exist',
    'connectiontext' => get_string('error_connection', $component),
];

$PAGE->requires->js_amd_inline('var networkChecksConfig = ' . json_encode($config) . ';' . <<<'JS'
require([networkChecksConfig.module, 'core/ajax', 'core/fragment', 'jquery'], function(Network, Ajax, Fragment, $) {
    var config = networkChecksConfig;
    var output = document.getElementById('network-results');
    var write = function(line) {
        output.textContent += line + '\n';
    };
    var check = function(name, condition, detail) {
        write(condition ? 'PASS ' + name : 'FAIL ' + name + ': ' + detail);
    };
    var sleep = function(ms) {
        return new Promise(function(resolve) {
            setTimeout(resolve, ms);
        });
    };

    // Count the requests core/ajax sends for one method and string id.
    var originalCall = Ajax.call;
    var sent = {};
    Ajax.call = function(requests) {
        requests.forEach(function(request) {
            var key = request.methodname + '/' + ((request.args && request.args.stringid) || '');
            sent[key] = (sent[key] || 0) + 1;
        });
        return originalCall.apply(this, arguments);
    };

    var connectionFailure = Network.connectionError('', 'network');
    var serverFailure = {errorcode: 'x', message: 'm'};

    var checks = [
        // (a) A server error passes through unchanged.
        function() {
            return Network.call({methodname: config.missingmethod, args: {}}).then(function() {
                check('a', false, 'resolved');
            }, function(err) {
                check('a', !!(err && err.errorcode) && !Network.isConnectionError(err),
                    'rejection ' + JSON.stringify(err));
            });
        },
        // (b) A request without an answer in time is a timeout connection error.
        function() {
            return Network.call({methodname: 'core_get_string', args: {stringid: 'ok', component: 'core'}},
                    {timeout: 1}).then(function() {
                check('b', false, 'resolved');
            }, function(err) {
                check('b', err && err.connection === true && err.reason === 'timeout',
                    'rejection ' + JSON.stringify(err));
            });
        },
        // (c) Classification of rejection values.
        function() {
            var connection = ['', 'timeout', new SyntaxError(), {}].every(Network.isConnectionError);
            var server = [{errorcode: 'x', message: 'm'}, new TypeError('bug')].every(function(err) {
                return !Network.isConnectionError(err);
            });
            check('c', connection && server, 'connection ' + connection + ', server ' + server);
        },
        // (d) Retry window.
        function() {
            var time = 0;
            var retry = Network.createRetryWindow({now: function() {
                return time;
            }});
            var results = [];
            results.push(retry.shouldRetry(connectionFailure));
            time = 89999;
            results.push(retry.shouldRetry(connectionFailure));
            time = 90000;
            results.push(retry.shouldRetry(connectionFailure));
            retry.succeeded();
            results.push(retry.shouldRetry(connectionFailure));
            results.push(retry.shouldRetry(serverFailure));
            check('d', results.join() === 'true,true,false,true,false', results.join());
        },
        // (e) Stall watch.
        function() {
            var time = 0;
            var now = function() {
                return time;
            };
            var waiting = Network.createStallWatch({now: now});
            var results = [];
            time = 59999;
            results.push(waiting.stalled());
            time = 60000;
            results.push(waiting.stalled());
            time = 0;
            var running = Network.createStallWatch({now: now});
            running.observe('step', true);
            time = 599999;
            results.push(running.stalled());
            time = 600000;
            results.push(running.stalled());
            check('e', results.join() === 'false,true,false,true', results.join());
        },
        // (f) Read retries.
        function() {
            var run = function(failures, failure) {
                var calls = 0;
                return Network.retryRead(function() {
                    calls++;
                    return calls <= failures ? Promise.reject(failure) : Promise.resolve('ok');
                }, {delayMs: 1}).then(function() {
                    return 'resolved after ' + calls;
                }, function() {
                    return 'rejected after ' + calls;
                });
            };
            return Promise.all([
                run(2, connectionFailure),
                run(1, serverFailure),
                run(5, connectionFailure),
            ]).then(function(results) {
                check('f', results.join() === 'resolved after 3,rejected after 1,rejected after 5', results.join());
            });
        },
        // (g) Single flight.
        function() {
            var calls = 0;
            var release = null;
            var guarded = Network.singleFlight(function() {
                calls++;
                return new Promise(function(resolve) {
                    release = resolve;
                });
            });
            guarded();
            guarded();
            return sleep(20).then(function() {
                check('g', calls === 1, calls + ' calls');
                release();
            });
        },
        // (h) Component messages.
        function() {
            return Network.forComponent({component: config.component}).connectionMessage().then(function(text) {
                check('h', text === config.connectiontext, text);
            }, function(err) {
                check('h', false, JSON.stringify(err));
            });
        },
        // (i) call() never resends, after a timeout or a server error.
        function() {
            var timeoutKey = 'core_get_string/pluginname';
            var serverKey = config.missingmethod + '/';
            sent[timeoutKey] = 0;
            sent[serverKey] = 0;
            var timedOut = Network.call({methodname: 'core_get_string',
                    args: {stringid: 'pluginname', component: config.component}}, {timeout: 1})
                .then(function() {
                    return false;
                }, Network.isConnectionError);
            var refused = Network.call({methodname: config.missingmethod, args: {}}).then(function() {
                return false;
            }, function(err) {
                return !!(err && err.errorcode);
            });
            return Promise.all([timedOut, refused]).then(function(results) {
                return sleep(500).then(function() {
                    check('i', results[0] && results[1] && sent[timeoutKey] === 1 && sent[serverKey] === 1,
                        'rejections ' + results.join() + ', sent ' + sent[timeoutKey] + '/' + sent[serverKey]);
                });
            });
        },
        // (j) Server text is shown as text, not markup.
        function() {
            return Network.forComponent({component: config.component}).notifyError({message: '<b>x</b>'})
                .then(function(modal) {
                    var body = modal && modal.getBody ? modal.getBody()[0] : null;
                    var ok = !!body && body.textContent.indexOf('<b>x</b>') !== -1 && !body.querySelector('b');
                    check('j', ok, body ? body.innerHTML : 'no modal');
                    if (modal) {
                        modal.destroy();
                    }
                });
        },
        // (k) The rejections of the Moodle 5.3 core/ajax for a lost connection are connection errors.
        function() {
            var failures = [
                new Error('HTTP 524: '),
                new TypeError('Failed to fetch'),
                new DOMException('signal is aborted without reason', 'AbortError'),
            ];
            var counting = Ajax.call;
            var reasons = failures.map(function(failure) {
                Ajax.call = function() {
                    return [Promise.reject(failure)];
                };
                return Network.call({methodname: 'core_get_string', args: {}}).then(function() {
                    return 'resolved';
                }, function(err) {
                    return err && err.connection === true ? err.reason : 'server';
                });
            });
            Ajax.call = counting;
            return Promise.all(reasons).then(function(results) {
                check('k', results.join() === 'network,network,timeout', results.join());
            });
        },
        // (l) core/fragment reads settled through the network module: retryRead() resends a transport
        // failure (4.x and 5.x forms) and gives up at once on a server error.
        function() {
            var counting = Ajax.call;
            var abort = new Error('The operation was aborted.');
            abort.name = 'AbortError';
            var run = function(failures, failure) {
                var calls = 0;
                var lastFailure = null;
                Ajax.call = function() {
                    calls++;
                    var deferred = $.Deferred();
                    if (calls <= failures) {
                        deferred.reject(failure);
                    } else {
                        deferred.resolve({html: '<p>ok</p>', javascript: ''});
                    }
                    return [deferred.promise()];
                };
                var load = function() {
                    return Network.settle(new Promise(function(resolve, reject) {
                        Fragment.loadFragment('core', 'stub', 1, {})
                            .done(function(html) {
                                resolve(html);
                            })
                            .fail(reject);
                    })).catch(function(err) {
                        lastFailure = err;
                        throw err;
                    });
                };
                return Network.retryRead(load, {delayMs: 1}).then(function(html) {
                    return html === '<p>ok</p>' ? 'resolved after ' + calls : 'resolved with ' + html;
                }, function(err) {
                    var kind = err && err.connection === true ? err.reason : 'server';
                    return kind + ' after ' + calls;
                }).then(function(result) {
                    var tagged = lastFailure && lastFailure.connection === true ? lastFailure.reason : 'server';
                    return result + ' (' + tagged + ')';
                });
            };
            var expected = [
                [1, new TypeError('Failed to fetch'), 'resolved after 2 (network)'],
                [1, new Error('HTTP 502: Bad Gateway'), 'resolved after 2 (network)'],
                [1, abort, 'resolved after 2 (timeout)'],
                [1, '', 'resolved after 2 (network)'],
                [5, new TypeError('Failed to fetch'), 'network after 5 (network)'],
                [1, {errorcode: 'x', message: 'm'}, 'server after 1 (server)'],
            ];
            return expected.reduce(function(chain, item) {
                return chain.then(function(results) {
                    return run(item[0], item[1]).then(function(result) {
                        return results.concat([result]);
                    });
                });
            }, Promise.resolve([])).then(function(results) {
                Ajax.call = counting;
                var want = expected.map(function(item) {
                    return item[2];
                });
                check('l', results.join() === want.join(), results.join());
            }, function(err) {
                Ajax.call = counting;
                throw err;
            });
        },
    ];

    checks.reduce(function(chain, next) {
        return chain.then(next).catch(function(err) {
            write('FAIL unexpected: ' + (err && err.message ? err.message : err));
        });
    }, Promise.resolve()).then(function() {
        Ajax.call = originalCall;
        write('DONE');
    });
});
JS
);

echo $OUTPUT->header();
echo html_writer::tag('pre', '', ['id' => 'network-results']);
echo $OUTPUT->footer();
