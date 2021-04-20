function addOrganizationForm($collectionHolder, $newLinkLi) {
    var prototype = $collectionHolder.data('prototype');
    var index = $collectionHolder.data('index');
    var newForm = prototype;
    newForm = newForm.replace(/__name__/g, index);
    $collectionHolder.data('index', index + 1);
    var $newFormLi = $('<li></li>').append(newForm);
    $newLinkLi.before($newFormLi);
}

function addOrganizationFormDeleteLink($organizationFormLi) {
    var $removeFormButton = $('<button type="button" class="btn btn-danger">Delete this organization</button>');
    $organizationFormLi.append($removeFormButton);

    $removeFormButton.on('click', function(e) {
        $organizationFormLi.remove();
    });
}

var $collectionHolder;
var $addOrganizationButton = $('<button type="button" class="btn-info btn add_organization_link">Add a linked organization</button>');
var $newLinkLi = $('<li></li>').append($addOrganizationButton);

jQuery(document).ready(function() {
    $collectionHolder = $('ul.linkedOrganizations');
    $collectionHolder.find('li').each(function() {
        addOrganizationFormDeleteLink($(this));
    });
    $collectionHolder.append($newLinkLi);
    $collectionHolder.data('index', $collectionHolder.find('input').length);
    $addOrganizationButton.on('click', function(e) {
        addOrganizationForm($collectionHolder, $newLinkLi);
    });
});

