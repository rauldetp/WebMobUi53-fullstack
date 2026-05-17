<x-vue-app-layout>
    <x-slot:title>Sondage</x-slot>

    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <div
        id="app-vote"
        data-props='@json(["token" => $token, "isAuthenticated" => $isAuthenticated, "loginUrl" => $loginUrl])'
    ></div>
</x-vue-app-layout>
