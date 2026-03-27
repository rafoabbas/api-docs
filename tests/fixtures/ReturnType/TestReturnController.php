<?php

declare(strict_types=1);

namespace ApiDocs\Tests\Fixtures\ReturnType;

class TestReturnController
{
    public function resourceMake()
    {
        $user = null;

        return TestUserResource::make($user);
    }

    public function resourceNew()
    {
        $user = null;

        return new TestUserResource($user);
    }

    public function resourceCollection()
    {
        $users = [];

        return TestUserResource::collection($users);
    }

    public function responseJson()
    {
        return response()->json([
            'message' => 'success',
            'status' => 'ok',
        ]);
    }

    public function arrayReturn()
    {
        return [
            'message' => 'hello',
            'success' => true,
        ];
    }

    public function withPaginate()
    {
        $users = null;

        return TestUserResource::collection($users->paginate(15));
    }

    public function withSimplePaginate()
    {
        $users = null;

        return TestUserResource::collection($users->simplePaginate(15));
    }

    public function withCursorPaginate()
    {
        $users = null;

        return TestUserResource::collection($users->cursorPaginate(15));
    }

    public function noReturn(): void
    {
        // Does nothing
    }

    public function stringReturn()
    {
        return 'hello';
    }
}
