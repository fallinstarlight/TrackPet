<?php

class UserServiceImp implements IUserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(string $username, string $password): UserResponse
    {
        $response = new UserResponse;
        
        $isEmail = LoginFunctions::isEmail($username);
        try {
            $loginInfo = $this->userRepository->getLoginInfo($username, $isEmail);
            if (isset($loginInfo['passwordHash'])) {
                $passwordHash = $loginInfo['passwordHash'];
                if (LoginFunctions::verify($password, $passwordHash)) {
                    $response->flag = UserResponse::FLAG_SUCCESS;
                    $response->message = "login successful";
                    $response->status = UserResponse::STATUS_SUCCESS;
                    LoginFunctions::login($loginInfo);
                    $response->data = [
                        "username" => $loginInfo["username"],
                        "name" => $loginInfo["name"],
                        "surname" => $loginInfo["surname"]
                    ];
                    $this->userRepository->setLastLogin($loginInfo['id']);
                } else {
                    $response->message = "invalid username or password";
                }
            } else {
                $response->message = "invalid username or password";
            }
        } catch (PDOException $e) {
            $response->message = "there was an unexpected error, please try again";
            $response->flag = UserResponse::FLAG_SERVER_ERROR;
        }
        return $response;
    }

    public function logout(): UserResponse
    {
        $response = new UserResponse;
        LoginFunctions::logout();
        $response->flag = UserResponse::FLAG_SUCCESS;
        $response->message= "logout succesful";
        $response->data = ["logout date"=>date('Y-m-d H:i:s')];
        return $response;
    }

    public function createUser(array $user): UserResponse
    {
        $response = new UserResponse;
        $user = new User($user);
        $validationErrors = $user->validateParams();
        if(!empty($validationErrors))
        {
            $response->message = "there are some errors with your request";
            $response->data = $validationErrors;
            return $response;
        }
        try{
            $result = $this->userRepository->createUser($user);
            if(isset($result['flag']) && $result['flag'] !== 0)
                $response->message = $result['message'];
            else{
                $response->status = UserResponse::STATUS_SUCCESS;
                $response->flag = UserResponse::FLAG_SUCCESS;
                $response->message = "Thank you for joining us";
                $response->data = $result;
            }
        }catch(PDOException $e){
            $response->flag = UserResponse::FLAG_SERVER_ERROR;
            $response->message = "There was an unexpected error";
        }
        return $response;
    }
}
