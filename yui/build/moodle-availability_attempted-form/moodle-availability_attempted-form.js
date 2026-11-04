YUI.add('moodle-availability_attempted-form', function (Y, NAME) {

/* eslint-disable camelcase */
/**
 * JavaScript for editing quiz attempted conditions.
 *
 * @module moodle-availability_attempted-form
 */
M.availability_attempted = M.availability_attempted || {};

/**
 * @class M.availability_attempted.form
 * @extends M.core_availability.plugin
 */
M.availability_attempted.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} cms Array of objects with id and name (already escaped by format_string)
 */
M.availability_attempted.form.initInner = function(cms) {
    this.cms = cms;
};

M.availability_attempted.form.getNode = function(json) {
    var label = M.util.get_string('label_cm', 'availability_attempted');
    var html = '<span class="col-form-label pe-3">' + M.util.get_string('title', 'availability_attempted') + '</span>' +
            ' <span class="availability-group mb-3"><label>' +
            '<span class="accesshide">' + label + ' </span>' +
            '<select class="form-select" name="cm" title="' + label + '">' +
            '<option value="0">' + M.util.get_string('choosedots', 'moodle') + '</option>';
    for (var i = 0; i < this.cms.length; i++) {
        html += '<option value="' + this.cms[i].id + '">' + this.cms[i].name + '</option>';
    }
    html += '</select></label></span>';
    var node = Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');

    if (json.cm !== undefined && node.one('select[name=cm] > option[value="' + json.cm + '"]')) {
        node.one('select[name=cm]').set('value', '' + json.cm);
    }

    if (!M.availability_attempted.form.addedEvents) {
        M.availability_attempted.form.addedEvents = true;
        Y.one('.availability-field').delegate('change', function() {
            M.core_availability.form.update();
        }, '.availability_attempted select');
    }
    return node;
};

M.availability_attempted.form.fillValue = function(value, node) {
    value.cm = parseInt(node.one('select[name=cm]').get('value'), 10);
};

M.availability_attempted.form.fillErrors = function(errors, node) {
    if (parseInt(node.one('select[name=cm]').get('value'), 10) === 0) {
        errors.push('availability_attempted:error_selectcmid');
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
