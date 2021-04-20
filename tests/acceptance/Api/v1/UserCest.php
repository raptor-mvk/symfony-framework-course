<?php

namespace AcceptanceTests\Api\v1;

use App\Tests\AcceptanceTester;
use Codeception\Util\HttpCode;

class UserCest
{
    public function testAddUserActionForAdmin(AcceptanceTester $I): void
    {
        $I->amAdmin();
        $I->sendPOST('/api/v1/user', $this->getAddUserParams());
        $I->canSeeResponseContainsJson(['success' => true]);
        $I->canSeeResponseMatchesJsonType(['success' => 'boolean', 'userId' => 'integer:>0']);
    }

    public function testAddUserActionForUser(AcceptanceTester $I): void
    {
        $I->amUser();
        $I->sendPOST('/api/v1/user', $this->getAddUserParams());
        $I->canSeeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    private function getAddUserParams(): array
    {
        return [
            'login' => 'my_user',
            'phone' => '+1111111111',
            'email' => 'no@mail.ru',
            'preferEmail' => 1
        ];
    }
}
