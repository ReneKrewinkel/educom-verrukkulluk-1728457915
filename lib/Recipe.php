<?php
require_once("lib/User.php");
require_once("lib/Ingredient.php");
require_once("lib/KitchenType.php");
require_once("lib/RecipeInfo.php");

class Recipe {

    private $connection;
    private $usr;
    private $ing;
    private $recInfo;
    private $kitcType;

    public function __construct($connection) {
        $this->connection = $connection;
        $this->usr      = new User($this->connection);
        $this->ing      = new Ingredient($this->connection);
        $this->recInfo  = new RecipeInfo($this->connection);
        $this->kitcType = new KitchenType($this->connection);
    }
  
    public function getRecipe($recipe_id = NULL) {

        $sql = "select * from gerecht";

        if($recipe_id != NULL) {
            $sql.= " where id = $recipe_id";
        }

        $result = mysqli_query($this->connection, $sql);

        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            
            $user        = $this->selectUser($row["user_id"]);
            $ingredients = $this->selectIngredient($row["id"]);
            $recipeInfo  = $this->selectRecipeInfo($row["id"]);
            $kitchen     = $this->selectKitchenOrType($row["keuken_id"]);
            $type        = $this->selectKitchenOrType($row["type_id"]);
            $favorites   = $this->determineFavorite($recipeInfo);
            $ratings     = $this->selectRating($recipeInfo);
            $remarks     = $this->selectRemarks($recipeInfo);
            $steps       = $this->selectSteps($recipeInfo);
            $price       = $this->calcPrice($ingredients);
            $calories    = $this->calcCalories($ingredients);
            $stars       = $this->calcAVGRating($ratings);

            $recipe[]= ["recipe"=>$row,
                        "user"=>$user,
                        "ingredients"=>$ingredients,
                        "kitchen"=>$kitchen,
                        "type"=>$type,
                        "favorites"=>$favorites,
                        "ratings"=>$ratings,
                        "remarks"=>$remarks,
                        "steps"=>$steps,
                        "price"=>$price,
                        "calories"=>$calories,
                        "stars"=>$stars];        
        }
        return $recipe;
    }

    private function selectUser($user_id) {
        $user = $this->usr->getUser($user_id);
        return $user;
    }

    private function selectIngredient($recipe_id) {
        $ingredient = $this->ing->getIngredients($recipe_id);
        return $ingredient;
    }
    
    private function selectRecipeInfo($recipe_id) {
        $recipeInfo = $this->recInfo->getRecipeInfo($recipe_id);
        return $recipeInfo;
    }

    private function selectKitchenOrType($kitchenOrType_id) {
        $kitchenType = $this->kitcType->getKitchenType($kitchenOrType_id);
        $kitchenOrType = $kitchenType["omschrijving"];
        return $kitchenOrType;
    }
    
    public function selectRating($allRecipeInfo) {
        $ratings = [];
        foreach ($allRecipeInfo as $recipeInfo) {
            foreach($recipeInfo as $r){
                if($r["record_type"]=="W") {
                    $ratings[] = $r;
                }
            }    
        }
        return $ratings;
    }
    
    private function selectSteps($allRecipeInfo) {
        $steps = [];
        foreach ($allRecipeInfo as $recipeInfo) {
            foreach($recipeInfo as $r) {
                if($r["record_type"]=="B") {
                    $steps[] = $r;
                }
            }    
        }
        return $steps;
    }
    
    private function selectRemarks($allRecipeInfo) {
        $remarks = [];
        foreach ($allRecipeInfo as $recipeInfo) {
            foreach($recipeInfo as $o) {
                if($o["record_type"]=="O"){
                    $remarks[] = $recipeInfo;
                }                
            }                    
        }
        return $remarks;
    }    
    
    private function determineFavorite($allRecipeInfo) {
        $favorites = [];
        foreach ($allRecipeInfo as $recipeInfo) {
            foreach($recipeInfo as $f) {
                if($f["record_type"]=="F"){
                    $favorites[] = $recipeInfo;
                }                
            }                  
        }
        return $favorites;
    }

    private function calcCalories($ingredients) {
        $calories = 0;
        foreach ($ingredients as $ingrAndArti) {
            foreach ($ingrAndArti as $i) {
                $needed = floatval($i["aantal"]);
                $package = floatval($i["verpakking"]);
                $packageCalories = floatval($i["calorieen"]);                            
            }
            $ingrCalories = ($packageCalories/$package)*$needed;
            $calories += $ingrCalories;
        }        
        return $calories;
    }  

    private function calcPrice($ingredients) {
        $price = 0;
        foreach ($ingredients as $ingrAndArti) {
            foreach ($ingrAndArti as $i) {
                $needed = floatval($i["aantal"]);
                $package = floatval($i["verpakking"]);
                $packagePrice = floatval($i["prijs"]);                       
            }
            //calculate price per ingredient, always round articles needed up
            //e.g., if you need 1.5 articles of some ingredient you need to buy 2
            $ingrPrice = ceil($needed/$package)*$packagePrice;
            $price += $ingrPrice;
        }      
        return $price;
    }

    public function calcAVGRating($ratings) {
        $ratingTotal = 0;
        $count = 0;
        foreach ($ratings as $rating) {
            $ratingTotal += floatval($rating["nummeriekveld"]);
            $count++;
        }
        $ratingAVG = $ratingTotal/$count;
        return $ratingAVG;
    }
}
